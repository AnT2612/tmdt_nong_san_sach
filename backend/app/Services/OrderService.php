<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\PointTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly FefoService $fefoService,
        private readonly ShippingService $shippingService,
    ) {
    }

    public function placeOrder(array $data, ?User $customer = null): Order
    {
        return DB::transaction(function () use ($data, $customer) {
            $subtotal = 0.0;
            $preparedItems = [];

            foreach ($data['items'] as $line) {
                $product = Product::whereKey($line['product_id'])
                    ->where('trang_thai', true)
                    ->first();

                if ($product === null) {
                    throw ValidationException::withMessages([
                        'items' => "Sản phẩm #{$line['product_id']} không tồn tại hoặc đã ngừng bán.",
                    ]);
                }

                $quantity = round((float) $line['quantity'], 2);
                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Số lượng của {$product->ten_san_pham} phải lớn hơn 0.",
                    ]);
                }

                $unitPrice = $product->currentPrice();
                $lineTotal = round($unitPrice * $quantity, 0);
                $subtotal += $lineTotal;

                $preparedItems[] = [
                    'product_id' => $product->id,
                    'so_luong'   => $quantity,
                    'don_gia'    => $unitPrice,
                ];
            }

            $deliveryType = $data['loai_giao'] ?? Order::LOAI_GIAO_THUONG;

            $order = Order::create([
                'ma_don'            => $this->generateOrderCode(),
                'loai_don'          => $data['loai_don'] ?? Order::LOAI_DON_LE,
                'user_id'           => $customer?->id,
                'subscription_id'   => $data['subscription_id'] ?? null,
                'complaint_id'      => $data['complaint_id'] ?? null,
                'ten_nguoi_nhan'    => $data['ten_nguoi_nhan'],
                'so_dien_thoai'     => $data['so_dien_thoai'],
                'email_nguoi_nhan'  => $data['email_nguoi_nhan'] ?? $customer?->email,
                'dia_chi_giao'      => $data['dia_chi_giao'],
                'loai_giao'         => $deliveryType,
                'phi_van_chuyen'    => 0,
                'khoang_cach_km'    => 0,
                'tong_tien'         => 0,
                'diem_su_dung'      => 0,
                'tien_giam_diem'    => 0,
                'trang_thai'        => Order::STATUS_CHO_DUYET,
                'ngay_giao_du_kien' => $data['ngay_giao_du_kien'] ?? now()->toDateString(),
            ]);

            $this->fefoService->allocateAndDeduct($order, $preparedItems);

            $actualSubtotal = (float) $order->orderDetails()->sum('thanh_tien');
            $shipping = $this->shippingService->calculate(
                $data['dia_chi_giao'],
                $actualSubtotal,
                $deliveryType,
                isset($data['vi_do']) ? (float) $data['vi_do'] : null,
                isset($data['kinh_do']) ? (float) $data['kinh_do'] : null,
            );

            $pointsUsed = 0;
            $discountAmount = 0.0;
            $requestedPoints = (int) ($data['diem_su_dung'] ?? 0);

            if ($customer !== null && $requestedPoints > 0) {
                $pointValue = (int) config('loyalty.point_value', 1000);
                $pointsUsed = min($requestedPoints, (int) $customer->diem_tich_luy);
                $maxByAmount = (int) floor(($actualSubtotal + $shipping['phi_van_chuyen']) / $pointValue);
                $pointsUsed = max(0, min($pointsUsed, $maxByAmount));
                $discountAmount = (float) ($pointsUsed * $pointValue);
            }

            $totalAmount = max(0, round($actualSubtotal + $shipping['phi_van_chuyen'] - $discountAmount, 0));

            $order->update([
                'phi_van_chuyen' => $shipping['phi_van_chuyen'],
                'khoang_cach_km' => $shipping['khoang_cach_km'],
                'tong_tien'      => $totalAmount,
                'diem_su_dung'   => $pointsUsed,
                'tien_giam_diem' => $discountAmount,
            ]);

            if ($customer !== null && $pointsUsed > 0) {
                $customer->decrement('diem_tich_luy', $pointsUsed);
                PointTransaction::create([
                    'user_id'        => $customer->id,
                    'order_id'       => $order->id,
                    'loai_giao_dich' => PointTransaction::LOAI_TRU,
                    'so_diem'        => $pointsUsed,
                    'mo_ta'          => "Dùng {$pointsUsed} điểm cho đơn {$order->ma_don}.",
                    'ngay_tao'       => now(),
                ]);
            }

            Payment::create([
                'order_id'        => $order->id,
                'cong_thanh_toan' => $data['cong_thanh_toan'] ?? Payment::CONG_COD,
                'so_tien'         => $totalAmount,
                'trang_thai'      => Payment::STATUS_CHO,
                'created_at'      => now(),
            ]);

            return $order->load(['orderDetails.product', 'orderDetails.batch', 'payments']);
        });
    }

    public function confirmOrder(Order $order): Order
    {
        if ($order->trang_thai !== Order::STATUS_CHO_DUYET) {
            throw ValidationException::withMessages([
                'trang_thai' => 'Chỉ có thể duyệt đơn đang ở trạng thái chờ duyệt.',
            ]);
        }

        return DB::transaction(function () use ($order) {
            if ($order->orderDetails()->whereNull('batch_id')->exists()) {
                $this->fefoService->allocateAndDeduct($order);
            }

            $order->trang_thai = Order::STATUS_DA_DUYET;
            $order->save();

            return $order->fresh(['orderDetails.product', 'orderDetails.batch', 'payments']);
        });
    }

    public function updateStatus(Order $order, string $newStatus): Order
    {
        return match ($newStatus) {
            Order::STATUS_DA_DUYET   => $this->confirmOrder($order),
            Order::STATUS_DANG_GIAO  => $this->markShipping($order),
            Order::STATUS_HOAN_THANH => $this->markDelivered($order),
            Order::STATUS_DA_HUY     => $this->cancelOrder($order),
            default => throw ValidationException::withMessages([
                'trang_thai' => 'Trạng thái đơn hàng không hợp lệ.',
            ]),
        };
    }

    public function markShipping(Order $order): Order
    {
        if ($order->trang_thai !== Order::STATUS_DA_DUYET) {
            throw ValidationException::withMessages([
                'trang_thai' => 'Chỉ có thể chuyển giao những đơn đã duyệt.',
            ]);
        }

        $order->trang_thai = Order::STATUS_DANG_GIAO;
        $order->save();

        return $order->fresh();
    }

    public function markDelivered(Order $order): Order
    {
        if (! in_array($order->trang_thai, [Order::STATUS_DA_DUYET, Order::STATUS_DANG_GIAO], true)) {
            throw ValidationException::withMessages([
                'trang_thai' => 'Đơn hàng chưa ở trạng thái hợp lệ để xác nhận hoàn thành.',
            ]);
        }

        return DB::transaction(function () use ($order) {
            $order->trang_thai = Order::STATUS_HOAN_THANH;
            $order->save();

            $latestPayment = $order->payments()->first();
            if ($latestPayment !== null && $latestPayment->cong_thanh_toan === Payment::CONG_COD && $latestPayment->trang_thai === Payment::STATUS_CHO) {
                $latestPayment->trang_thai = Payment::STATUS_THANH_CONG;
                $latestPayment->save();
            }

            if ($order->deliverySchedule !== null) {
                $order->deliverySchedule->trang_thai = 'da_giao';
                $order->deliverySchedule->save();
            }

            $this->awardLoyaltyPoints($order);

            return $order->fresh(['orderDetails.product', 'orderDetails.batch', 'payments']);
        });
    }

    public function cancelOrder(Order $order): Order
    {
        if (in_array($order->trang_thai, [Order::STATUS_HOAN_THANH, Order::STATUS_DA_HUY], true)) {
            throw ValidationException::withMessages([
                'trang_thai' => 'Đơn đã hoàn thành hoặc đã hủy thì không thể hủy thêm.',
            ]);
        }

        return DB::transaction(function () use ($order) {
            if ($order->orderDetails()->whereNotNull('batch_id')->exists()) {
                $this->fefoService->restoreOrderStock($order);
            }

            $order->trang_thai = Order::STATUS_DA_HUY;
            $order->save();

            if ($order->diem_su_dung > 0 && $order->user_id !== null) {
                $customer = User::find($order->user_id);
                if ($customer !== null) {
                    $customer->increment('diem_tich_luy', $order->diem_su_dung);
                    PointTransaction::create([
                        'user_id'        => $customer->id,
                        'order_id'       => $order->id,
                        'loai_giao_dich' => PointTransaction::LOAI_HOAN,
                        'so_diem'        => $order->diem_su_dung,
                        'mo_ta'          => "Hoàn điểm do hủy đơn {$order->ma_don}.",
                        'ngay_tao'       => now(),
                    ]);
                }
            }

            return $order->fresh();
        });
    }

    private function awardLoyaltyPoints(Order $order): void
    {
        if ($order->user_id === null || $order->loai_don === Order::LOAI_GIAO_BU) {
            return;
        }

        $divisor = (int) config('loyalty.earn_divisor', 10000);
        $productTotal = max(0.0, (float) $order->tong_tien - (float) $order->phi_van_chuyen);
        $points = (int) floor($productTotal / $divisor);

        if ($points <= 0) {
            return;
        }

        $alreadyEarned = PointTransaction::where('order_id', $order->id)
            ->where('loai_giao_dich', PointTransaction::LOAI_CONG)
            ->exists();

        if ($alreadyEarned) {
            return;
        }

        $customer = User::find($order->user_id);
        if ($customer !== null) {
            $customer->increment('diem_tich_luy', $points);
            PointTransaction::create([
                'user_id'        => $customer->id,
                'order_id'       => $order->id,
                'loai_giao_dich' => PointTransaction::LOAI_CONG,
                'so_diem'        => $points,
                'mo_ta'          => "Tích điểm từ đơn {$order->ma_don}.",
                'ngay_tao'       => now(),
            ]);
        }
    }

    private function generateOrderCode(): string
    {
        $prefix = 'ORD-' . now()->format('Ymd') . '-';
        $count = Order::whereDate('ngay_tao', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}

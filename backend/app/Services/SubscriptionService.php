<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\DeliverySchedule;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function subscribe(User $customer, array $planConfig, array $data): Subscription
    {
        $start = Carbon::parse($data['ngay_bat_dau'] ?? now())->startOfDay();
        $days  = (int) ($planConfig['thoi_han_ngay'] ?? 30);
        $end   = $start->copy()->addDays($days);

        $weekdaysStr = $data['ngay_giao_trong_tuan'] ?? $planConfig['ngay_giao_trong_tuan'] ?? '3,6';
        $weekdays = array_values(array_filter(array_map('intval', explode(',', (string) $weekdaysStr))));

        if (empty($weekdays)) {
            throw ValidationException::withMessages([
                'ngay_giao_trong_tuan' => 'Vui lòng chọn ít nhất một thứ giao hàng trong tuần.',
            ]);
        }

        return DB::transaction(function () use ($customer, $planConfig, $data, $start, $end, $weekdaysStr, $weekdays) {
            $subscription = Subscription::create([
                'user_id'              => $customer->id,
                'ten_goi'              => $planConfig['ten_goi'],
                'ngay_giao_trong_tuan' => $weekdaysStr,
                'ngay_bat_dau'         => $start->toDateString(),
                'ngay_ket_thuc'        => $end->toDateString(),
                'dia_chi_giao'         => $data['dia_chi_giao'],
                'trang_thai'           => Subscription::STATUS_ACTIVE,
            ]);

            foreach ($planConfig['items'] as $line) {
                $product = Product::where('sku', $line['sku'])->first();
                if ($product !== null) {
                    SubscriptionItem::create([
                        'subscription_id' => $subscription->id,
                        'product_id'      => $product->id,
                        'so_luong'        => $line['so_luong'],
                    ]);
                }
            }

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $vnWeekday = $cursor->dayOfWeekIso === 7 ? 7 : $cursor->dayOfWeekIso + 1;
                if (in_array($vnWeekday, $weekdays, true)) {
                    DeliverySchedule::create([
                        'subscription_id' => $subscription->id,
                        'ngay_giao'       => $cursor->toDateString(),
                        'khung_gio'       => $data['khung_gio'] ?? '08:00-10:00',
                        'trang_thai'      => DeliverySchedule::STATUS_CHO_SINH_DON,
                    ]);
                }
                $cursor->addDay();
            }

            Payment::create([
                'subscription_id' => $subscription->id,
                'cong_thanh_toan' => $data['cong_thanh_toan'] ?? Payment::CONG_VNPAY,
                'ma_giao_dich'    => 'SUB-' . $subscription->id . '-' . time(),
                'so_tien'         => $planConfig['gia_goi'],
                'trang_thai'      => Payment::STATUS_THANH_CONG,
                'created_at'      => now(),
            ]);

            return $subscription->load(['items.product', 'deliverySchedules']);
        });
    }

    public function generateDueOrders(): array
    {
        return Cache::lock('subscription-generate-orders', 60)
            ->block(5, fn () => $this->processDueSchedules());
    }

    private function processDueSchedules(): array
    {
        $targetDate = now()->toDateString();

        $schedules = DeliverySchedule::query()
            ->where('trang_thai', DeliverySchedule::STATUS_CHO_SINH_DON)
            ->whereDate('ngay_giao', '<=', $targetDate)
            ->whereHas('subscription', fn ($q) => $q->where('trang_thai', Subscription::STATUS_ACTIVE))
            ->with(['subscription.user', 'subscription.items.product'])
            ->get();

        $createdOrders = [];

        foreach ($schedules as $schedule) {
            $subscription = $schedule->subscription;
            $items = $subscription->items->map(fn (SubscriptionItem $item) => [
                'product_id' => $item->product_id,
                'quantity'   => (float) $item->so_luong,
            ])->all();

            try {
                $order = DB::transaction(function () use ($subscription, $schedule, $items) {
                    $order = $this->orderService->placeOrder([
                        'loai_don'          => Order::LOAI_DINH_KY,
                        'subscription_id'   => $subscription->id,
                        'ten_nguoi_nhan'    => $subscription->user?->ho_ten ?? 'Khách gói định kỳ',
                        'so_dien_thoai'     => $subscription->user?->so_dien_thoai ?? '0900000000',
                        'email_nguoi_nhan'  => $subscription->user?->email,
                        'dia_chi_giao'      => $subscription->dia_chi_giao,
                        'cong_thanh_toan'   => Payment::CONG_VNPAY,
                        'ngay_giao_du_kien' => $schedule->ngay_giao->toDateString(),
                        'items'             => $items,
                    ], $subscription->user);

                    $order->phi_van_chuyen = 0;
                    $order->tong_tien      = 0;
                    $order->save();

                    $order = $this->orderService->confirmOrder($order);

                    $schedule->order_id   = $order->id;
                    $schedule->trang_thai = DeliverySchedule::STATUS_DA_SINH_DON;
                    $schedule->save();

                    return $order;
                });

                $createdOrders[] = $order;
            } catch (ValidationException|InsufficientStockException $e) {
                $schedule->trang_thai = DeliverySchedule::STATUS_THIEU_HANG;
                $schedule->save();
            }
        }

        return $createdOrders;
    }

    public function skipSchedule(Subscription $subscription): ?DeliverySchedule
    {
        $next = $subscription->deliverySchedules()
            ->whereIn('trang_thai', [DeliverySchedule::STATUS_CHO_SINH_DON, DeliverySchedule::STATUS_THIEU_HANG])
            ->first();

        if ($next !== null) {
            $next->trang_thai = DeliverySchedule::STATUS_BO_QUA;
            $next->save();
        }

        return $next;
    }
}

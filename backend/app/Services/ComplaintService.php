<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplaintService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function createComplaint(
        User $customer,
        OrderDetail $orderDetail,
        string $lyDo,
        ?string $noiDung,
        string $minhChung
    ): Complaint {
        $orderDetail->loadMissing('order');
        $order = $orderDetail->order;

        if ($order === null || $order->user_id !== $customer->id) {
            throw ValidationException::withMessages([
                'order_detail_id' => 'Bạn không có quyền khiếu nại sản phẩm này.',
            ]);
        }

        if ($order->trang_thai !== Order::STATUS_HOAN_THANH) {
            throw ValidationException::withMessages([
                'order_detail_id' => 'Chỉ có thể khiếu nại đơn hàng đã hoàn thành giao.',
            ]);
        }

        $deliveredAt = $order->ngay_cap_nhat ?? $order->ngay_tao;
        if ($deliveredAt !== null && $deliveredAt->copy()->addHours(24)->isPast()) {
            throw ValidationException::withMessages([
                'order_detail_id' => 'Đã quá 24 giờ kể từ khi giao hàng nên không thể gửi khiếu nại.',
            ]);
        }

        if (trim($minhChung) === '') {
            throw ValidationException::withMessages([
                'minh_chung' => 'Vui lòng đính kèm ảnh hoặc video minh chứng.',
            ]);
        }

        $exists = Complaint::where('order_detail_id', $orderDetail->id)->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'order_detail_id' => 'Dòng hàng này đã có yêu cầu khiếu nại trước đó.',
            ]);
        }

        return Complaint::create([
            'order_detail_id' => $orderDetail->id,
            'user_id'         => $customer->id,
            'ly_do'           => $lyDo,
            'noi_dung'        => $noiDung,
            'minh_chung'      => $minhChung,
            'trang_thai'      => Complaint::STATUS_MOI,
        ]);
    }

    public function handle(
        Complaint $complaint,
        User $sales,
        string $huongXuLy,
        float $soTienHoan = 0
    ): Complaint {
        $allowed = [
            Complaint::HUONG_HOAN_TIEN,
            Complaint::HUONG_GIAO_BU,
            Complaint::HUONG_TU_CHOI,
        ];

        if (! in_array($huongXuLy, $allowed, true)) {
            throw ValidationException::withMessages([
                'huong_xu_ly' => 'Hướng xử lý không hợp lệ.',
            ]);
        }

        if ($complaint->trang_thai === Complaint::STATUS_DA_XU_LY) {
            throw ValidationException::withMessages([
                'trang_thai' => 'Khiếu nại này đã được xử lý trước đó.',
            ]);
        }

        return DB::transaction(function () use ($complaint, $sales, $huongXuLy, $soTienHoan) {
            $complaint->loadMissing('orderDetail.order');
            $detail = $complaint->orderDetail;
            $order  = $detail?->order;

            $complaint->huong_xu_ly = $huongXuLy;
            $complaint->trang_thai  = Complaint::STATUS_DA_XU_LY;
            $complaint->xu_ly_boi   = $sales->id;

            if ($huongXuLy === Complaint::HUONG_HOAN_TIEN) {
                $complaint->so_tien_hoan = $soTienHoan > 0
                    ? $soTienHoan
                    : (float) ($detail?->thanh_tien ?? $order?->tong_tien ?? 0);

                if ($order !== null) {
                    $payment = Payment::where('order_id', $order->id)->first();
                    if ($payment !== null) {
                        $payment->trang_thai = Payment::STATUS_HOAN_TIEN;
                        $payment->save();
                    }
                }
            }

            if ($huongXuLy === Complaint::HUONG_GIAO_BU && $order !== null && $detail !== null) {
                $replacement = $this->orderService->placeOrder([
                    'loai_don'         => Order::LOAI_GIAO_BU,
                    'complaint_id'     => $complaint->id,
                    'ten_nguoi_nhan'   => $order->ten_nguoi_nhan,
                    'so_dien_thoai'    => $order->so_dien_thoai,
                    'email_nguoi_nhan' => $order->email_nguoi_nhan,
                    'dia_chi_giao'     => $order->dia_chi_giao,
                    'loai_giao'        => Order::LOAI_GIAO_HOA_TOC,
                    'cong_thanh_toan'  => Payment::CONG_COD,
                    'items'            => [
                        [
                            'product_id' => $detail->product_id,
                            'quantity'   => (float) $detail->so_luong,
                        ],
                    ],
                ], $order->user);

                $replacement->phi_van_chuyen = 0;
                $replacement->tong_tien      = 0;
                $replacement->save();

                $this->orderService->confirmOrder($replacement);
            }

            $complaint->save();

            return $complaint->fresh(['orderDetail.order', 'orderDetail.product', 'orderDetail.batch', 'replacementOrder']);
        });
    }
}

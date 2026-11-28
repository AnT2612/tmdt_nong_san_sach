<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['orderDetails.product:id,ten_san_pham,don_vi_tinh', 'orderDetails.batch:id,ma_lo,ngay_het_han', 'payments'])
            ->when($request->filled('trang_thai'), fn ($q) => $q->where('trang_thai', $request->input('trang_thai')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $kw = $request->string('search')->toString();
                $q->where(function ($sub) use ($kw) {
                    $sub->where('ma_don', 'like', "%{$kw}%")
                        ->orWhere('so_dien_thoai', 'like', "%{$kw}%")
                        ->orWhere('ten_nguoi_nhan', 'like', "%{$kw}%");
                });
            })
            ->orderByRaw("CASE trang_thai WHEN 'cho_duyet' THEN 1 WHEN 'da_duyet' THEN 2 WHEN 'dang_giao' THEN 3 WHEN 'hoan_thanh' THEN 4 ELSE 5 END")
            ->latest('ngay_tao')
            ->paginate(max(1, (int) $request->input('per_page', 15)));

        return $this->paginated($orders, 'Lấy danh sách đơn hàng thành công.');
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'trang_thai' => ['required', 'in:da_duyet,dang_giao,hoan_thanh,da_huy'],
        ]);

        $order = Order::find($id);
        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        $order = $this->orderService->updateStatus($order, $validated['trang_thai']);

        return $this->success($order, 'Cập nhật trạng thái đơn hàng thành công.');
    }

    public function confirm(int $id): JsonResponse
    {
        $order = Order::find($id);
        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        $order = $this->orderService->confirmOrder($order);

        return $this->success([
            'order'       => $order,
            'allocations' => $order->orderDetails->map(fn ($detail) => [
                'ma_lo'        => $detail->batch?->ma_lo,
                'ngay_het_han' => $detail->batch?->ngay_het_han?->format('d/m/Y'),
                'so_luong'     => (float) $detail->so_luong,
            ])->toArray(),
        ], 'Duyệt đơn và trừ kho FEFO thành công.');
    }

    public function ship(int $id): JsonResponse
    {
        $order = Order::find($id);
        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        return $this->success($this->orderService->markShipping($order), 'Đơn hàng đang được giao.');
    }

    public function deliver(int $id): JsonResponse
    {
        $order = Order::find($id);
        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        return $this->success($this->orderService->markDelivered($order), 'Giao hàng thành công. Đã cộng điểm tích luỹ.');
    }

    public function cancel(int $id): JsonResponse
    {
        $order = Order::find($id);
        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        return $this->success($this->orderService->cancelOrder($order), 'Đã huỷ đơn hàng và hoàn hàng về lô.');
    }
}

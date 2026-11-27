<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    // Danh sách đơn cho sales
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with('items')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $keyword = $request->string('search')->toString();

                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('order_code', 'like', "%{$keyword}%")
                        ->orWhere('receiver_phone', 'like', "%{$keyword}%")
                        ->orWhere('receiver_name', 'like', "%{$keyword}%");
                });
            })
            ->orderByRaw("FIELD(status, 'pending', 'confirmed', 'shipping', 'delivered', 'cancelled')")
            ->latest()
            ->paginate(max(1, (int) $request->input('per_page', 15)));

        return $this->paginated($orders, 'Lấy danh sách đơn hàng thành công.');
    }

    // Duyệt đơn - chạy FEFO
    public function confirm(int $id): JsonResponse
    {
        $order = Order::find($id);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        $order = $this->orderService->confirmOrder($order, Auth::guard('api')->user());

        return $this->success([
            'order'       => $order,
            'allocations' => $this->summariseAllocations($order),
        ], 'Duyệt đơn thành công. Hàng đã được trừ khỏi kho theo FEFO.');
    }

    // Chuyển sang đang giao
    public function ship(int $id): JsonResponse
    {
        $order = Order::find($id);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        if ($order->status !== Order::STATUS_CONFIRMED) {
            return $this->error('Chỉ có thể giao những đơn đã được duyệt.', 422);
        }

        $order->status = Order::STATUS_SHIPPING;
        $order->save();

        return $this->success($order, 'Đơn hàng đang được giao.');
    }

    // Xác nhận giao thành công
    public function deliver(int $id): JsonResponse
    {
        $order = Order::find($id);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        if ($order->status !== Order::STATUS_SHIPPING) {
            return $this->error('Đơn hàng chưa ở trạng thái đang giao.', 422);
        }

        $order = $this->orderService->markDelivered($order, Auth::guard('api')->user());

        return $this->success($order, 'Giao hàng thành công. Điểm tích luỹ đã được cộng cho khách.');
    }

    // Sales huỷ đơn
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do huỷ đơn.',
        ]);

        $order = Order::find($id);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        $order = $this->orderService->cancelOrder($order, Auth::guard('api')->user(), $validated['reason']);

        return $this->success($order, 'Đã huỷ đơn hàng và hoàn hàng về kho.');
    }

    // Tóm tắt phân bổ lô cho dễ đọc trên màn hình Sales:
    // "Lấy 2kg từ LO-... (hạn 20/11), 3kg từ LO-... (hạn 25/11)".
    private function summariseAllocations(Order $order): array
    {
        return $order->batchAllocations->map(fn ($allocation) => [
            'batch_code'  => $allocation->batch?->batch_code,
            'expiry_date' => $allocation->batch?->expiry_date?->format('d/m/Y'),
            'quantity'    => (float) $allocation->quantity,
        ])->toArray();
    }
}

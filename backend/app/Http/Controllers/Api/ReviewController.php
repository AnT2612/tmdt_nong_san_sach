<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'order_id'   => ['required', 'integer', 'exists:orders,id'],
            'so_sao'     => ['required', 'integer', 'between:1,5'],
            'noi_dung'   => ['nullable', 'string', 'max:2000'],
        ]);

        $userId = Auth::guard('api')->id();
        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $userId)
            ->where('trang_thai', Order::STATUS_HOAN_THANH)
            ->first();

        if ($order === null) {
            return $this->error('Chỉ có thể đánh giá sản phẩm thuộc đơn hàng đã hoàn thành của bạn.', 422);
        }

        $exists = Review::where('user_id', $userId)
            ->where('order_id', $order->id)
            ->where('product_id', $validated['product_id'])
            ->exists();

        if ($exists) {
            return $this->error('Bạn đã đánh giá sản phẩm này cho đơn hàng này rồi.', 422);
        }

        $review = Review::create([
            'user_id'    => $userId,
            'product_id' => $validated['product_id'],
            'order_id'   => $order->id,
            'so_sao'     => $validated['so_sao'],
            'noi_dung'   => $validated['noi_dung'] ?? null,
            'created_at' => now(),
        ]);

        return $this->success($review->load('user:id,ho_ten'), 'Đã gửi đánh giá sản phẩm.', 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CartController extends Controller
{
    use ApiResponse;

    /**
     * Lấy giỏ hàng hiện tại của khách hàng đang đăng nhập.
     */
    public function index(): JsonResponse
    {
        $items = $this->getCartItems(Auth::guard('api')->id());

        return $this->success([
            'items'    => array_values($items),
            'subtotal' => $this->calculateSubtotal($items),
        ], 'Lấy giỏ hàng thành công.');
    }

    /**
     * Thêm sản phẩm vào giỏ hàng server-side.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'numeric', 'min:0.01'],
        ], [
            'product_id.required' => 'Vui lòng chọn sản phẩm.',
            'quantity.required'   => 'Vui lòng nhập số lượng.',
            'quantity.min'        => 'Số lượng phải lớn hơn 0.',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $userId  = Auth::guard('api')->id();
        $items   = $this->getCartItems($userId);

        $quantity = round((float) $validated['quantity'], 2);
        $existingKey = null;
        foreach ($items as $index => $item) {
            if ((int) $item['product_id'] === (int) $product->id) {
                $existingKey = $index;
                break;
            }
        }

        if ($existingKey !== null) {
            $newQty = round((float) $items[$existingKey]['quantity'] + $quantity, 2);
            $items[$existingKey]['quantity']   = $newQty;
            $items[$existingKey]['don_gia']    = $product->currentPrice();
            $items[$existingKey]['thanh_tien'] = round($newQty * $product->currentPrice(), 0);
            $savedItem = $items[$existingKey];
        } else {
            $nextId = empty($items) ? 1 : (max(array_column($items, 'id')) + 1);
            $price = $product->currentPrice();
            $savedItem = [
                'id'           => $nextId,
                'product_id'   => $product->id,
                'ten_san_pham' => $product->ten_san_pham,
                'don_vi_tinh'  => $product->don_vi_tinh,
                'quantity'     => $quantity,
                'don_gia'      => $price,
                'thanh_tien'   => round($quantity * $price, 0),
            ];
            $items[] = $savedItem;
        }

        $this->saveCartItems($userId, $items);

        return $this->success([
            'item'     => $savedItem,
            'items'    => array_values($items),
            'subtotal' => $this->calculateSubtotal($items),
        ], 'Đã thêm sản phẩm vào giỏ hàng.', 201);
    }

    /**
     * Cập nhật số lượng của một dòng trong giỏ hàng.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $userId = Auth::guard('api')->id();
        $items  = $this->getCartItems($userId);

        foreach ($items as $index => $item) {
            if ((int) $item['id'] === $id) {
                $qty = round((float) $validated['quantity'], 2);
                $items[$index]['quantity']   = $qty;
                $items[$index]['thanh_tien'] = round($qty * (float) $item['don_gia'], 0);
                $this->saveCartItems($userId, $items);

                return $this->success([
                    'item'     => $items[$index],
                    'items'    => array_values($items),
                    'subtotal' => $this->calculateSubtotal($items),
                ], 'Cập nhật giỏ hàng thành công.');
            }
        }

        return $this->error('Không tìm thấy sản phẩm trong giỏ hàng.', 404);
    }

    /**
     * Xoá một dòng sản phẩm khỏi giỏ hàng theo id.
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = Auth::guard('api')->id();
        $items  = $this->getCartItems($userId);

        $remaining = [];
        $deleted   = null;

        foreach ($items as $item) {
            if ((int) $item['id'] === $id) {
                $deleted = $item;
                continue;
            }
            $remaining[] = $item;
        }

        if ($deleted === null) {
            return $this->error('Không tìm thấy sản phẩm trong giỏ hàng.', 404);
        }

        $this->saveCartItems($userId, $remaining);

        return $this->success([
            'deleted_item_id' => $id,
            'items'           => array_values($remaining),
            'subtotal'        => $this->calculateSubtotal($remaining),
        ], 'Đã xoá sản phẩm khỏi giỏ hàng.');
    }

    private function cacheKey(int|string $userId): string
    {
        return "cart_items_user_{$userId}";
    }

    private function getCartItems(int|string $userId): array
    {
        $items = Cache::get($this->cacheKey($userId), []);
        return is_array($items) ? $items : [];
    }

    private function saveCartItems(int|string $userId, array $items): void
    {
        Cache::forever($this->cacheKey($userId), array_values($items));
    }

    private function calculateSubtotal(array $items): float
    {
        return round(array_sum(array_map(fn (array $item) => (float) ($item['thanh_tien'] ?? 0), $items)), 0);
    }
}

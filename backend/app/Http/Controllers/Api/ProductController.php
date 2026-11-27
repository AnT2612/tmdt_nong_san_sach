<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with('category:id,ten_danh_muc,slug')
            ->where('trang_thai', true);

        if ($request->filled('category')) {
            $category = $request->string('category')->toString();
            $query->whereHas('category', function ($subQuery) use ($category) {
                $subQuery->where('slug', $category)->orWhere('id', $category);
            });
        }

        if ($request->filled('min_price')) {
            $query->where('gia_ban_co_ban', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('gia_ban_co_ban', '<=', (float) $request->input('max_price'));
        }

        if ($request->filled('certification')) {
            $query->where('chung_nhan', 'like', '%' . $request->input('certification') . '%');
        }

        if ($request->filled('search')) {
            $keyword = $request->string('search')->toString();
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('ten_san_pham', 'like', "%{$keyword}%")
                    ->orWhere('mo_ta', 'like', "%{$keyword}%");
            });
        }

        match ($request->input('sort', 'newest')) {
            'price_asc'  => $query->orderBy('gia_ban_co_ban'),
            'price_desc' => $query->orderByDesc('gia_ban_co_ban'),
            'name_asc'   => $query->orderBy('ten_san_pham'),
            default      => $query->orderByDesc('id'),
        };

        $perPage = max(1, min((int) $request->input('per_page', 12), 50));
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(fn (Product $product) => $this->formatProduct($product));

        return $this->paginated($products, 'Lấy danh sách sản phẩm thành công.');
    }

    public function show(string $slugOrId): JsonResponse
    {
        $product = Product::query()
            ->with([
                'category:id,ten_danh_muc,slug',
                'reviews' => fn ($query) => $query->latest('created_at')->limit(10),
                'reviews.user:id,ho_ten',
            ])
            ->where('trang_thai', true)
            ->where(function ($query) use ($slugOrId) {
                $query->where('slug', $slugOrId)->orWhere('id', $slugOrId);
            })
            ->first();

        if ($product === null) {
            return $this->error('Không tìm thấy sản phẩm.', 404);
        }

        $data = $this->formatProduct($product);
        $data['mo_ta']          = $product->mo_ta;
        $data['reviews']        = $product->reviews;
        $data['average_rating'] = round((float) $product->reviews->avg('so_sao'), 1);

        return $this->success($data, 'Lấy chi tiết sản phẩm thành công.');
    }

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('trang_thai', true)
            ->orderBy('ten_danh_muc')
            ->get();

        return $this->success($categories, 'Lấy danh mục thành công.');
    }

    public function availableBatches(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 8), 40));

        $batches = Batch::query()
            ->with('product:id,ten_san_pham,slug,don_vi_tinh,gia_ban_co_ban,hinh_anh_dai_dien')
            ->available()
            ->fefoOrder()
            ->limit($limit)
            ->get()
            ->map(fn (Batch $batch) => [
                'id'               => $batch->id,
                'ma_lo'            => $batch->ma_lo,
                'qr_code'          => $batch->qr_code,
                'ten_san_pham'     => $batch->product?->ten_san_pham,
                'product_slug'     => $batch->product?->slug,
                'don_vi_tinh'      => $batch->product?->don_vi_tinh,
                'ngay_thu_hoach'   => $batch->ngay_thu_hoach?->format('d/m/Y'),
                'ngay_het_han'     => $batch->ngay_het_han?->format('d/m/Y'),
                'days_until_expiry'=> $batch->daysUntilExpiry(),
                'so_luong_ton'     => (float) $batch->so_luong_ton,
                'gia_xa_hang'      => $batch->gia_xa_hang !== null ? (float) $batch->gia_xa_hang : null,
                'gia_ap_dung'      => $batch->effectivePrice(),
                'trang_thai'       => $batch->trang_thai,
            ]);

        return $this->success($batches, 'Lấy bảng lô đang bán thành công.');
    }

    private function formatProduct(Product $product): array
    {
        return [
            'id'                 => $product->id,
            'ten_san_pham'       => $product->ten_san_pham,
            'slug'               => $product->slug,
            'sku'                => $product->sku,
            'don_vi_tinh'        => $product->don_vi_tinh,
            'gia_ban_co_ban'     => (float) $product->gia_ban_co_ban,
            'gia_hien_tai'       => $product->currentPrice(),
            'hinh_anh_dai_dien'  => $product->hinh_anh_dai_dien,
            'chung_nhan'         => $product->chung_nhan,
            'category'           => $product->category?->only(['id', 'ten_danh_muc', 'slug']),
            'available_quantity' => $product->totalAvailableQuantity(),
            'in_stock'           => $product->totalAvailableQuantity() > 0,
        ];
    }
}

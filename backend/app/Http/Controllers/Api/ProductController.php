<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProductController extends Controller
{
    use ApiResponse;

    // Danh sách sản phẩm
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with('category:id,name,slug')
            ->where('is_active', true);

        if ($request->filled('category')) {
            $category = $request->string('category')->toString();

            $query->whereHas('category', function ($subQuery) use ($category) {
                $subQuery->where('slug', $category)->orWhere('id', $category);
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        if ($request->filled('certification')) {
            $query->where('certification_summary', 'like', '%' . $request->input('certification') . '%');
        }

        if ($request->filled('search')) {
            $keyword = $request->string('search')->toString();

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        match ($request->input('sort', 'newest')) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name_asc'   => $query->orderBy('name'),
            default      => $query->orderByDesc('id'),
        };

        $perPage = max(1, min((int) $request->input('per_page', 12), 50));
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(function (Product $product) {
            return $this->formatProduct($product);
        });

        return $this->paginated($products, 'Lấy danh sách sản phẩm thành công.');
    }

    // Chi tiết sản phẩm
    public function show(string $slugOrId): JsonResponse
    {
        $with = ['category:id,name,slug'];
        $hasReviews = Schema::hasTable('reviews');

        if ($hasReviews) {
            $with['reviews'] = fn ($query) => $query->where('is_visible', true)->latest()->limit(10);
            $with[] = 'reviews.user:id,full_name,avatar_url';
        }

        $product = Product::query()
            ->with($with)
            ->where('is_active', true)
            ->where(function ($query) use ($slugOrId) {
                $query->where('slug', $slugOrId)->orWhere('id', $slugOrId);
            })
            ->first();

        if ($product === null) {
            return $this->error('Không tìm thấy sản phẩm.', 404);
        }

        $data = $this->formatProduct($product);
        $data['description']    = $product->description;
        $data['gallery']        = $product->gallery ?? [];
        $data['origin']         = $product->origin;
        $data['reviews']        = $hasReviews ? $product->reviews : [];
        $data['average_rating'] = $hasReviews ? round((float) $product->reviews->avg('rating'), 1) : 0.0;

        return $this->success($data, 'Lấy chi tiết sản phẩm thành công.');
    }

    // Danh sách danh mục
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success($categories, 'Lấy danh mục thành công.');
    }

    // Bảng lô đang bán (công khai, không cần đăng nhập)
    public function availableBatches(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 8), 40));

        $batches = \App\Models\Batch::query()
            ->with('product:id,name,slug,unit,thumbnail_url,shelf_life_days')
            ->available()
            ->fefoOrder()
            ->limit($limit)
            ->get()
            ->map(fn (\App\Models\Batch $batch) => [
                'batch_code'         => $batch->batch_code,
                'traceability_token' => $batch->traceability_token,
                'product_name'       => $batch->product?->name,
                'product_slug'       => $batch->product?->slug,
                'unit'               => $batch->product?->unit,
                'harvest_date'       => $batch->harvest_date?->format('d/m/Y'),
                'expiry_date'        => $batch->expiry_date?->format('d/m/Y'),
                'days_until_expiry'  => $batch->daysUntilExpiry(),
                'quantity_available' => (float) $batch->quantity_available,
            ]);

        return $this->success($batches, 'Lấy bảng lô đang bán thành công.');
    }

    private function formatProduct(Product $product): array
    {
        return [
            'id'                 => $product->id,
            'name'               => $product->name,
            'slug'               => $product->slug,
            'sku'                => $product->sku,
            'unit'               => $product->unit,
            'price'              => (float) $product->price,
            'sale_price'         => $product->sale_price !== null ? (float) $product->sale_price : null,
            'current_price'      => $product->currentPrice(),
            'thumbnail_url'      => $product->thumbnail_url,
            'origin'             => $product->origin,
            'certification'      => $product->certification_summary,
            'category'           => $product->category?->only(['id', 'name', 'slug']),
            'available_quantity' => $product->totalAvailableQuantity(),
            'in_stock'           => $product->totalAvailableQuantity() > 0,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
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


        // Lọc theo khoảng giá.
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        // Lọc theo chứng nhận (VietGAP, USDA Organic...).
        if ($request->filled('certification')) {
            $query->where('certification_summary', 'like', '%' . $request->input('certification') . '%');
        }

        // Tìm kiếm theo tên hoặc mô tả.
        if ($request->filled('search')) {
            $keyword = $request->string('search')->toString();

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // Sắp xếp: mặc định mới nhất; hỗ trợ giá tăng/giảm.
        match ($request->input('sort', 'newest')) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name_asc'   => $query->orderBy('name'),
=======
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


        // Bổ sung tồn kho khả dụng cho từng sản phẩm trong danh sách.
        $products->getCollection()->transform(function (Product $product) {
            return $this->formatProduct($product);
        });

        $products->getCollection()->transform(fn (Product $product) => $this->formatProduct($product));


        return $this->paginated($products, 'Lấy danh sách sản phẩm thành công.');
    }


    // Chi tiết sản phẩm


    public function show(string $slugOrId): JsonResponse
    {
        $product = Product::query()
            ->with([

                'category:id,name,slug',
                'reviews' => fn ($query) => $query->where('is_visible', true)->latest()->limit(10),
                'reviews.user:id,full_name,avatar_url',
            ])
            ->where('is_active', true)

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

        $data['description'] = $product->description;
        $data['gallery']     = $product->gallery ?? [];
        $data['origin']      = $product->origin;
        $data['reviews']     = $product->reviews;
        $data['average_rating'] = round((float) $product->reviews->avg('rating'), 1);

        $data['mo_ta']          = $product->mo_ta;
        $data['reviews']        = $product->reviews;
        $data['average_rating'] = round((float) $product->reviews->avg('so_sao'), 1);


        return $this->success($data, 'Lấy chi tiết sản phẩm thành công.');
    }


    // Danh sách danh mục
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('trang_thai', true)
            ->orderBy('ten_danh_muc')

            ->get();

        return $this->success($categories, 'Lấy danh mục thành công.');
    }


    // Bảng lô đang bán (công khai, không cần đăng nhập)
    //
    // Đây là dữ liệu cho bảng "Hôm nay xuất vườn" ở trang chủ
    // Chỉ trả những thông tin khách được thấy: mã lô, tên sản phẩm,
    // số lượng còn lại, hạn sử dụng. KHÔNG trả số điện thoại nhân viên,
    // không trả thẻ kho, không trả giá vốn


    public function availableBatches(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 8), 40));


        $batches = \App\Models\Batch::query()
            ->with('product:id,name,slug,unit,thumbnail_url,shelf_life_days')

        $batches = Batch::query()
            ->with('product:id,ten_san_pham,slug,don_vi_tinh,gia_ban_co_ban,hinh_anh_dai_dien')

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


    // Định dạng lại dữ liệu sản phẩm trả về cho Frontend
    // Cố tình chỉ trả các trường cần thiết, không trả toàn bộ Model

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

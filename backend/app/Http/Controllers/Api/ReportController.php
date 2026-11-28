<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Order;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;

use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\FefoService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(

        private readonly InventoryService $inventoryService,
    ) {
    }

    // Bốn con số tổng quan cho trang dashboard

        private readonly FefoService $fefoService,
    ) {
    }


    public function overview(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->toDateString());


        // Doanh thu chỉ tính đơn đã giao thành công.
        $revenue = (float) Order::query()
            ->where('status', Order::STATUS_DELIVERED)
            ->whereBetween(DB::raw('DATE(delivered_at)'), [$from, $to])
            ->sum('total_amount');

        $orderCounts = Order::query()
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $customerCount = User::where('role', User::ROLE_CUSTOMER)->count();

        $loss = $this->inventoryService->lossRate($from, $to);

        return $this->success([
            'period'   => ['from' => $from, 'to' => $to],
            'revenue'  => $revenue,
            'orders'   => $orderCounts,
            'customers' => $customerCount,
            'loss'     => $loss,
        ], 'Lấy số liệu tổng quan thành công.');
    }

    // Doanh thu theo ngày

        $revenue = (float) Order::query()
            ->where('trang_thai', Order::STATUS_HOAN_THANH)
            ->whereBetween(DB::raw('DATE(ngay_tao)'), [$from, $to])
            ->sum('tong_tien');

        $orderCounts = Order::query()
            ->whereBetween(DB::raw('DATE(ngay_tao)'), [$from, $to])
            ->selectRaw('trang_thai, COUNT(*) as total')
            ->groupBy('trang_thai')
            ->pluck('total', 'trang_thai');

        $customerCount = User::whereHas('role', fn ($q) => $q->where('ten_vai_tro', Role::CUSTOMER))->count();

        $loss = $this->fefoService->lossRate($from, $to);

        return $this->success([
            'period'    => ['from' => $from, 'to' => $to],
            'revenue'   => $revenue,
            'orders'    => $orderCounts,
            'customers' => $customerCount,
            'loss'      => $loss,
        ], 'Lấy số liệu tổng quan thành công.');
    }


    public function revenue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to   = $validated['to'] ?? now()->toDateString();

        $daily = Order::query()

            ->where('status', Order::STATUS_DELIVERED)
            ->whereBetween(DB::raw('DATE(delivered_at)'), [$from, $to])
            ->selectRaw('DATE(delivered_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy(DB::raw('DATE(delivered_at)'))

            ->where('trang_thai', Order::STATUS_HOAN_THANH)
            ->whereBetween(DB::raw('DATE(ngay_tao)'), [$from, $to])
            ->selectRaw('DATE(ngay_tao) as date, COUNT(*) as orders, SUM(tong_tien) as revenue')
            ->groupBy(DB::raw('DATE(ngay_tao)'))

            ->orderBy('date')
            ->get();

        return $this->success($daily, 'Lấy doanh thu theo ngày thành công.');
    }


    // Tình hình tồn kho hiện tại
    public function inventory(): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with('category:id,name')

    public function inventory(): JsonResponse
    {
        $products = Product::query()
            ->where('trang_thai', true)
            ->with('category:id,ten_danh_muc')

            ->get()
            ->map(function (Product $product) {
                $availableBatches = $product->availableBatches()->get();

                return [

                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'unit'         => $product->unit,
                    'category'     => $product->category?->name,
                    'total_available' => round($availableBatches->sum('quantity_available'), 3),
                    'batch_count'  => $availableBatches->count(),
                    // Lô gần hết hạn nhất - cảnh báo cho Admin.
                    'nearest_expiry' => $availableBatches->first()?->expiry_date?->format('d/m/Y'),
                ];
            });

        $expiringSoon = Batch::query()
            ->available()
            ->with('product:id,name')
            ->whereDate('expiry_date', '<=', now()->addDays(3)->toDateString())
            ->fefoOrder()
            ->get()
            ->map(fn (Batch $batch) => [
                'batch_code'       => $batch->batch_code,
                'product_name'     => $batch->product?->name,
                'quantity_available' => (float) $batch->quantity_available,
                'expiry_date'      => $batch->expiry_date?->format('d/m/Y'),

                    'product_id'      => $product->id,
                    'ten_san_pham'    => $product->ten_san_pham,
                    'don_vi_tinh'     => $product->don_vi_tinh,
                    'category'        => $product->category?->ten_danh_muc,
                    'total_available' => round((float) $availableBatches->sum('so_luong_ton'), 2),
                    'batch_count'     => $availableBatches->count(),
                    'nearest_expiry'  => $availableBatches->first()?->ngay_het_han?->format('d/m/Y'),
                ];
            });

        $expiringSoon = $this->fefoService->batchesExpiringSoon(2)
            ->map(fn (Batch $batch) => [
                'id'                => $batch->id,
                'ma_lo'             => $batch->ma_lo,
                'ten_san_pham'      => $batch->product?->ten_san_pham,
                'so_luong_ton'      => (float) $batch->so_luong_ton,
                'ngay_het_han'      => $batch->ngay_het_han?->format('d/m/Y'),

                'days_until_expiry' => $batch->daysUntilExpiry(),
            ]);

        return $this->success([
            'products'      => $products,
            'expiring_soon' => $expiringSoon,
        ], 'Lấy tình hình tồn kho thành công.');
    }


    // Sản phẩm bán chạy

    public function topProducts(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 10), 50));


        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) as total_quantity, SUM(order_items.line_total) as total_revenue')
            ->groupBy('order_items.product_id', 'order_items.product_name')

        $topProducts = OrderDetail::query()
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->where('orders.trang_thai', Order::STATUS_HOAN_THANH)
            ->selectRaw('order_details.product_id, products.ten_san_pham, SUM(order_details.so_luong) as total_quantity, SUM(order_details.thanh_tien) as total_revenue')
            ->groupBy('order_details.product_id', 'products.ten_san_pham')

            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return $this->success($topProducts, 'Lấy danh sách sản phẩm bán chạy thành công.');
    }
}

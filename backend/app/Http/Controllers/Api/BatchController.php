<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BatchController extends Controller
{
    use ApiResponse;

    // Danh sách lô hàng
    public function index(Request $request): JsonResponse
    {
        $batches = Batch::query()
            ->with('product:id,name,unit,thumbnail_url')
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->input('product_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $keyword = $request->string('search')->toString();
                $query->where('batch_code', 'like', "%{$keyword}%");
            })
            ->fefoOrder()
            ->paginate(max(1, (int) $request->input('per_page', 15)));

        $batches->getCollection()->transform(function (Batch $batch) {
            $batch->days_until_expiry = $batch->daysUntilExpiry();
            return $batch;
        });

        return $this->paginated($batches, 'Lấy danh sách lô hàng thành công.');
    }

    // Tạo lô hàng mới (lúc bắt đầu gieo trồng)
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'planted_date' => ['required', 'date', 'before_or_equal:today'],
            'quality_note' => ['nullable', 'string', 'max:255'],
        ], [
            'product_id.required'          => 'Vui lòng chọn sản phẩm cho lô hàng.',
            'planted_date.required'        => 'Vui lòng nhập ngày gieo trồng.',
            'planted_date.before_or_equal' => 'Ngày gieo trồng không được ở tương lai.',
        ]);

        $token = Str::random(48);
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        $batch = Batch::create([
            'product_id'         => $validated['product_id'],
            'batch_code'         => $this->generateBatchCode(),
            'traceability_token' => $token,
            'farm_staff_id'      => Auth::guard('api')->id(),
            'planted_date'       => $validated['planted_date'],
            'quantity_imported'  => 0,
            'quantity_available' => 0,
            'status'             => Batch::STATUS_GROWING,
            'quality_note'       => $validated['quality_note'] ?? null,
        ]);

        return $this->success([
            'batch'  => $batch->load('product'),
            'qr_url' => $frontendUrl . '/truy-xuat/' . $token,
        ], 'Tạo lô hàng thành công.', 201);
    }

    // Sinh mã lô dạng LO-20261115-001
    private function generateBatchCode(): string
    {
        $prefix = 'LO-' . now()->format('Ymd') . '-';

        $todayCount = Batch::whereDate('created_at', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $todayCount, 3, '0', STR_PAD_LEFT);
    }
}

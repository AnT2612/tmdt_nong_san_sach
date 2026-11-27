<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchAlert;
use App\Models\LossRecord;
use App\Services\FefoService;
use App\Services\QrGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class BatchController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly FefoService $fefoService,
        private readonly QrGeneratorService $qrService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $batches = Batch::query()
            ->with(['product:id,ten_san_pham,don_vi_tinh,gia_ban_co_ban,hinh_anh_dai_dien', 'creator:id,ho_ten'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->input('product_id')))
            ->when($request->filled('trang_thai'), fn ($q) => $q->where('trang_thai', $request->input('trang_thai')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $kw = $request->string('search')->toString();
                $q->where('ma_lo', 'like', "%{$kw}%");
            })
            ->fefoOrder()
            ->paginate(max(1, (int) $request->input('per_page', 15)));

        $batches->getCollection()->transform(function (Batch $batch) {
            $batch->days_until_expiry = $batch->daysUntilExpiry();
            return $batch;
        });

        return $this->paginated($batches, 'Lấy danh sách lô hàng thành công.');
    }

    public function show(int $id): JsonResponse
    {
        $batch = Batch::with([
            'product:id,ten_san_pham,don_vi_tinh,gia_ban_co_ban',
            'creator:id,ho_ten',
            'farmingLogs.farmStaff:id,ho_ten',
            'lossRecords.creator:id,ho_ten',
            'certificates.uploader:id,ho_ten',
            'alerts',
        ])->find($id);

        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        return $this->success($batch, 'Lấy chi tiết lô hàng thành công.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'     => ['required', 'integer', 'exists:products,id'],
            'ngay_thu_hoach' => ['required', 'date'],
            'ngay_het_han'   => ['required', 'date', 'after_or_equal:ngay_thu_hoach'],
            'so_luong_nhap'  => ['required', 'numeric', 'min:0.01'],
            'gia_xa_hang'    => ['nullable', 'numeric', 'min:0'],
        ], [
            'product_id.required'         => 'Vui lòng chọn sản phẩm.',
            'ngay_thu_hoach.required'     => 'Vui lòng nhập ngày thu hoạch.',
            'ngay_het_han.required'       => 'Vui lòng nhập ngày hết hạn.',
            'ngay_het_han.after_or_equal' => 'Ngày hết hạn phải lớn hơn hoặc bằng ngày thu hoạch.',
            'so_luong_nhap.required'      => 'Vui lòng nhập số lượng nhập kho.',
            'so_luong_nhap.min'           => 'Số lượng nhập kho phải lớn hơn 0.',
        ]);

        $maLo = $this->generateBatchCode();
        $daysUntil = now()->startOfDay()->diffInDays(
            \Carbon\Carbon::parse($validated['ngay_het_han'])->startOfDay(),
            false
        );

        $status = $daysUntil < 0
            ? Batch::STATUS_HET_HAN
            : ($daysUntil <= 2 ? Batch::STATUS_CAN_HAN : Batch::STATUS_CON_HANG);

        $batch = Batch::create([
            'product_id'       => $validated['product_id'],
            'ma_lo'            => $maLo,
            'ngay_thu_hoach'   => $validated['ngay_thu_hoach'],
            'ngay_het_han'     => $validated['ngay_het_han'],
            'so_luong_nhap'    => round((float) $validated['so_luong_nhap'], 2),
            'so_luong_ton'     => round((float) $validated['so_luong_nhap'], 2),
            'so_luong_hao_hut' => 0,
            'qr_code'          => url('/truy-xuat/' . $maLo),
            'trang_thai'       => $status,
            'gia_xa_hang'      => $validated['gia_xa_hang'] ?? null,
            'created_by'       => Auth::guard('api')->id(),
        ]);

        $this->qrService->generateForBatch($batch);

        if ($status === Batch::STATUS_CAN_HAN) {
            BatchAlert::create([
                'batch_id'      => $batch->id,
                'ngay_canh_bao' => now(),
                'trang_thai'    => BatchAlert::STATUS_DANG_MO,
                'batch_dang_mo' => $batch->id,
            ]);
        }

        return $this->success([
            'batch'  => $batch->load('product'),
            'qr_url' => $this->qrService->publicUrl($batch),
        ], 'Tạo lô hàng thành công.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $batch = Batch::find($id);
        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $validated = $request->validate([
            'ngay_thu_hoach' => ['required', 'date'],
            'ngay_het_han'   => ['required', 'date', 'after_or_equal:ngay_thu_hoach'],
            'so_luong_nhap'  => ['required', 'numeric', 'min:0.01'],
            'gia_xa_hang'    => ['nullable', 'numeric', 'min:0'],
            'trang_thai'     => ['sometimes', 'in:con_hang,can_han,het_hang,het_han'],
        ]);

        $soldOrLost = max(0, (float) $batch->so_luong_nhap - (float) $batch->so_luong_ton);
        $newImport  = round((float) $validated['so_luong_nhap'], 2);

        if ($newImport < $soldOrLost) {
            return $this->error('Số lượng nhập mới không được nhỏ hơn lượng đã xuất/hao hụt.', 422);
        }

        $batch->ngay_thu_hoach = $validated['ngay_thu_hoach'];
        $batch->ngay_het_han   = $validated['ngay_het_han'];
        $batch->so_luong_nhap  = $newImport;
        $batch->so_luong_ton   = round($newImport - $soldOrLost, 2);
        $batch->gia_xa_hang    = $validated['gia_xa_hang'] ?? $batch->gia_xa_hang;

        if (isset($validated['trang_thai'])) {
            $batch->trang_thai = $validated['trang_thai'];
        }

        $batch->save();

        return $this->success($batch->fresh('product'), 'Cập nhật thông tin lô hàng thành công.');
    }

    public function applyFlashSale(Request $request, int $id): JsonResponse
    {
        $batch = Batch::with('product')->find($id);
        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $validated = $request->validate([
            'gia_xa_hang' => ['required', 'numeric', 'min:0'],
        ], [
            'gia_xa_hang.required' => 'Vui lòng nhập giá xả hàng.',
        ]);

        $batch->gia_xa_hang = $validated['gia_xa_hang'];
        $batch->save();

        $openAlert = BatchAlert::where('batch_dang_mo', $batch->id)->first();
        if ($openAlert !== null) {
            $openAlert->trang_thai      = BatchAlert::STATUS_DA_XU_LY;
            $openAlert->handled_by      = Auth::guard('api')->id();
            $openAlert->hanh_dong_xu_ly = 'Áp giá xả hàng ' . number_format((float) $validated['gia_xa_hang']) . 'đ';
            $openAlert->batch_dang_mo   = null;
            $openAlert->save();
        }

        return $this->success($batch->fresh('product'), 'Đã áp dụng giá xả hàng cho lô.');
    }

    public function destroy(int $id): JsonResponse
    {
        $batch = Batch::find($id);
        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        if ($batch->orderDetails()->exists() || $batch->lossRecords()->exists()) {
            return $this->error('Không thể xoá lô hàng đã phát sinh đơn hàng hoặc phiếu hao hụt.', 422);
        }

        $batch->delete();

        return $this->success(null, 'Đã xoá lô hàng.');
    }

    public function dispose(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'so_luong' => ['required', 'numeric', 'min:0.01'],
            'ly_do'    => ['required', 'string', 'max:255'],
            'hinh_anh' => ['nullable', 'string', 'max:255'],
        ], [
            'so_luong.required' => 'Vui lòng nhập số lượng xuất hủy.',
            'ly_do.required'    => 'Vui lòng nhập lý do xuất hủy.',
        ]);

        $batch = Batch::find($id);
        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        try {
            $lossRecord = $this->fefoService->recordLoss(
                $batch,
                (float) $validated['so_luong'],
                $validated['ly_do'],
                Auth::guard('api')->user(),
                $validated['hinh_anh'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success([
            'loss_record' => $lossRecord,
            'batch'       => $batch->fresh('product'),
        ], 'Đã ghi nhận phiếu xuất hủy / hao hụt.');
    }

    public function expiringSoon(Request $request): JsonResponse
    {
        $withinDays = (int) $request->input('days', 2);
        $this->fefoService->scanAndCreateAlerts($withinDays);

        $batches = $this->fefoService->batchesExpiringSoon($withinDays);

        return $this->success(
            $batches->map(fn (Batch $batch) => [
                'id'                => $batch->id,
                'ma_lo'             => $batch->ma_lo,
                'ten_san_pham'      => $batch->product?->ten_san_pham,
                'don_vi_tinh'       => $batch->product?->don_vi_tinh,
                'so_luong_ton'      => (float) $batch->so_luong_ton,
                'gia_ban_co_ban'    => (float) ($batch->product?->gia_ban_co_ban ?? 0),
                'gia_xa_hang'       => $batch->gia_xa_hang !== null ? (float) $batch->gia_xa_hang : null,
                'ngay_het_han'      => $batch->ngay_het_han?->format('d/m/Y'),
                'days_until_expiry' => $batch->daysUntilExpiry(),
                'trang_thai'        => $batch->trang_thai,
            ]),
            "Có {$batches->count()} lô sắp hết hạn trong {$withinDays} ngày tới."
        );
    }

    public function lossRecords(int $id): JsonResponse
    {
        $batch = Batch::with('product:id,ten_san_pham,don_vi_tinh')->find($id);
        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $records = LossRecord::query()
            ->with('creator:id,ho_ten')
            ->where('batch_id', $id)
            ->latest('created_at')
            ->get();

        return $this->success([
            'batch'        => $batch,
            'loss_records' => $records,
        ], 'Lấy lịch sử hao hụt thành công.');
    }

    public function alerts(): JsonResponse
    {
        $alerts = BatchAlert::query()
            ->with(['batch.product:id,ten_san_pham,don_vi_tinh,gia_ban_co_ban', 'handler:id,ho_ten'])
            ->latest('ngay_canh_bao')
            ->get();

        return $this->success($alerts, 'Lấy danh sách cảnh báo lô cận hạn thành công.');
    }

    private function generateBatchCode(): string
    {
        $prefix = 'LO-' . now()->format('Ymd') . '-';
        $count  = Batch::whereDate('created_at', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }
}

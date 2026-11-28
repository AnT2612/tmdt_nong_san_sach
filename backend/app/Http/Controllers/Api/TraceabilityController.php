<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\QrGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TraceabilityController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly QrGeneratorService $qrService,
    ) {
    }

    public function show(string $code): JsonResponse
    {
        $batch = $this->qrService->findByCode($code);

        if ($batch === null) {
            return $this->error('Mã QR không hợp lệ hoặc lô hàng không tồn tại.', 404);
        }

        $product = $batch->product;

        return $this->success([
            'batch' => [
                'id'                => $batch->id,
                'ma_lo'             => $batch->ma_lo,
                'qr_code'           => $batch->qr_code,
                'ngay_thu_hoach'    => $batch->ngay_thu_hoach?->format('d/m/Y'),
                'ngay_het_han'      => $batch->ngay_het_han?->format('d/m/Y'),
                'days_until_expiry' => $batch->daysUntilExpiry(),
                'trang_thai'        => $batch->trang_thai,
            ],
            'product' => [
                'ten_san_pham'      => $product?->ten_san_pham,
                'don_vi_tinh'       => $product?->don_vi_tinh,
                'hinh_anh_dai_dien' => $product?->hinh_anh_dai_dien,
                'chung_nhan'        => $product?->chung_nhan,
                'ten_danh_muc'      => $product?->category?->ten_danh_muc,
            ],
            'farmer' => [
                'ho_ten' => $batch->creator?->ho_ten,
            ],
            'timeline' => $batch->farmingLogs->map(fn ($log) => [
                'id'                  => $log->id,
                'hanh_dong'           => $log->hanh_dong,
                'mo_ta'               => $log->mo_ta,
                'hinh_anh_minh_chung' => $log->hinh_anh_minh_chung,
                'thoi_gian'           => $log->thoi_gian?->format('d/m/Y H:i'),
                'farm_staff'          => $log->farmStaff?->ho_ten,
            ])->toArray(),
            'certificates' => $batch->certificates->map(fn ($cert) => [
                'id'              => $cert->id,
                'ten_chung_nhan'  => $cert->ten_chung_nhan,
                'file_minh_chung' => $cert->file_minh_chung,
                'ngay_cap'        => $cert->ngay_cap?->format('d/m/Y'),
                'ngay_het_han'    => $cert->ngay_het_han?->format('d/m/Y'),
                'uploaded_by'     => $cert->uploader?->ho_ten,
            ])->toArray(),
        ], 'Lấy hồ sơ truy xuất nguồn gốc thành công.');
    }

    public function qrCode(string $code): Response
    {
        $batch = $this->qrService->findByCode($code);

        if ($batch === null) {
            return response('Không tìm thấy lô hàng.', 404);
        }

        $svg = $this->qrService->qrSvg($batch);

        return response($svg, 200, [
            'Content-Type'        => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-' . $batch->ma_lo . '.svg"',
        ]);
    }
}

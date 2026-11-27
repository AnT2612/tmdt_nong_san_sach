<?php

namespace App\Services;

use App\Models\Batch;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

class QrGeneratorService
{
    public function publicUrl(Batch $batch): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        return $frontendUrl . '/truy-xuat/' . $batch->ma_lo;
    }

    public function generateForBatch(Batch $batch): string
    {
        $traceUrl = url('/truy-xuat/' . $batch->ma_lo);
        $filePath = 'qrcodes/batch-' . $batch->ma_lo . '.svg';

        $svg = $this->qrSvg($batch);
        Storage::disk('public')->put($filePath, $svg);

        if (empty($batch->qr_code)) {
            $batch->update(['qr_code' => $traceUrl]);
        }

        return $batch->qr_code ?: $traceUrl;
    }

    public function qrSvg(Batch $batch, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd(),
        );

        $writer = new Writer($renderer);

        return $writer->writeString($this->publicUrl($batch));
    }

    public function findByCode(string $code): ?Batch
    {
        return Batch::query()
            ->where('ma_lo', $code)
            ->orWhere('qr_code', $code)
            ->orWhere('qr_code', 'like', '%/' . $code)
            ->with([
                'product.category',
                'creator:id,ho_ten',
                'farmingLogs.farmStaff:id,ho_ten',
                'certificates.uploader:id,ho_ten',
            ])
            ->first();
    }
}

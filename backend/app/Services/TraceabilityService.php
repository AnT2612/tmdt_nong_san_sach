<?php

namespace App\Services;

use App\Models\Batch;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

class TraceabilityService
{
    // Đường dẫn công khai mà mã QR sẽ trỏ tới
    public function publicUrl(Batch $batch): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        return $frontendUrl . '/truy-xuat/' . $batch->traceability_token;
    }

    // Sinh ảnh QR dạng chuỗi SVG
    // Frontend có thể nhận chuỗi này và chèn thẳng vào thẻ <img src="data:image/svg+xml...">.
    public function qrSvg(Batch $batch, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd(),
        );

        $writer = new Writer($renderer);

        return $writer->writeString($this->publicUrl($batch));
    }

    // Sinh token bí mật cho một lô mới
    // Tách thành hàm riêng để BatchController gọi khi tạo lô
    public function generateToken(): string
    {
        return Str::random(48);
    }

    // Tìm lô theo token hoặc mã lô (batch_code)
    // Dùng cho trang tra cứu công khai: khách quét mã QR (chứa token)
    // hoặc nhập mã lô in trên tem bao bì (batch_code) đều tìm thấy
    public function findByToken(string $token): ?Batch
    {
        return Batch::query()
            ->where('traceability_token', $token)
            ->orWhere('batch_code', $token)
            ->with([
                'product.category',
                'farmStaff:id,full_name',
                'farmingLogs.author:id,full_name',
            ])
            ->first();
    }
}

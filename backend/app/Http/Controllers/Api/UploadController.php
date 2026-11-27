<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * PHỐI HỢP: Trần Thanh An (0306241171 - Khiếu nại), Nguyễn Thanh Liêm (0306241214 - Nhật ký canh tác)
 * CHỨC NĂNG: Controller tiếp nhận tệp tải lên (ảnh / video) và đẩy lên Cloudinary.
 *
 *   Mục tiêu:
 *     - Nhận tệp từ giao diện (ảnh hoặc video minh chứng).
 *     - Kiểm tra định dạng và dung lượng theo chuẩn đặc tả UC-03 Tuần 2:
 *         + Ảnh: JPG, PNG, WEBP <= 5 MB.
 *         + Video: MP4, MOV <= 30 MB.
 *     - Đẩy trực tiếp lên dịch vụ Cloudinary để tối ưu băng thông máy chủ.
 *     - Kế hoạch rủi ro kỹ thuật (Tuần 1): Nếu chưa cấu hình Cloudinary hoặc lỗi mạng,
 *       hệ thống tự động lưu vào storage cục bộ (local fallback) để đảm bảo không gián đoạn đồ án.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UploadController extends Controller
{
    use ApiResponse;

    /**
     * TẢI LÊN TỆP MINH CHỨNG (ẢNH HOẶC VIDEO).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,mp4,mov',
                'max:30720', // Tối đa 30MB (cho video), ảnh sẽ kiểm tra thêm ở dưới
            ],
        ], [
            'file.required' => 'Vui lòng chọn tệp cần tải lên.',
            'file.mimes'    => 'Định dạng tệp không được hỗ trợ. Chỉ chấp nhận ảnh (JPG, PNG, WEBP) hoặc video (MP4, MOV).',
            'file.max'      => 'Dung lượng tệp vượt quá giới hạn tối đa cho phép (30MB).',
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType() ?? '';
        $isImage = str_starts_with($mime, 'image/');

        // Kiểm tra dung lượng riêng cho ảnh: tối đa 5MB theo UC-03
        if ($isImage && $file->getSize() > 5 * 1024 * 1024) {
            return $this->error('Dung lượng hình ảnh không được vượt quá 5MB theo quy định.', 422);
        }

        // Lấy cấu hình Cloudinary từ file config/services.php
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        // 1. Thử tải lên Cloudinary nếu đã điền thông tin cấu hình
        if (!empty($cloudName) && !empty($apiKey) && !empty($apiSecret)) {
            try {
                $timestamp = time();
                $folder = 'nong_san_sach';
                
                // Tạo chữ ký (signature) xác thực bảo mật theo chuẩn Cloudinary API
                $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
                $signature = sha1($paramsToSign . $apiSecret);

                $resourceType = $isImage ? 'image' : 'video';
                $cloudinaryUrl = "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload";

                $response = Http::timeout(60)
                    ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
                    ->post($cloudinaryUrl, [
                        'api_key'   => $apiKey,
                        'timestamp' => $timestamp,
                        'folder'    => $folder,
                        'signature' => $signature,
                    ]);

                if ($response->successful()) {
                    $secureUrl = $response->json('secure_url');
                    return $this->success([
                        'url'           => $secureUrl,
                        'public_id'     => $response->json('public_id'),
                        'resource_type' => $resourceType,
                        'storage'       => 'cloudinary',
                    ], 'Tải tệp lên Cloudinary thành công.');
                }

                Log::warning('Cloudinary upload thất bại, chuyển sang phương án lưu trữ nội bộ: ' . $response->body());
            } catch (Throwable $e) {
                Log::error('Lỗi kết nối Cloudinary: ' . $e->getMessage());
            }
        }

        // 2. Phương án dự phòng (Fallback): Lưu vào thư mục public của Laravel
        try {
            $extension = $file->getClientOriginalExtension();
            $filename = Str::random(24) . '.' . $extension;
            $path = $file->storeAs('uploads', $filename, 'public');
            $localUrl = url('storage/' . $path);

            return $this->success([
                'url'           => $localUrl,
                'resource_type' => $isImage ? 'image' : 'video',
                'storage'       => 'local',
            ], 'Tải tệp lên máy chủ lưu trữ thành công.');
        } catch (Throwable $e) {
            return $this->error('Không thể lưu trữ tệp: ' . $e->getMessage(), 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CertificateController extends Controller
{
    use ApiResponse;

    public function index(int $batchId): JsonResponse
    {
        $batch = Batch::find($batchId);
        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $certificates = Certificate::query()
            ->with('uploader:id,ho_ten')
            ->where('batch_id', $batchId)
            ->latest('ngay_cap')
            ->get();

        return $this->success($certificates, 'Lấy giấy tờ kiểm định thành công.');
    }

    public function store(Request $request, int $batchId): JsonResponse
    {
        $batch = Batch::find($batchId);
        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $validated = $request->validate([
            'ten_chung_nhan'  => ['required', 'string', 'max:150'],
            'file_minh_chung' => ['required', 'url', 'max:255'],
            'ngay_cap'        => ['required', 'date'],
            'ngay_het_han'    => ['nullable', 'date', 'after_or_equal:ngay_cap'],
        ]);

        $cert = Certificate::create([
            'batch_id'        => $batchId,
            'uploaded_by'     => Auth::guard('api')->id(),
            'ten_chung_nhan'  => $validated['ten_chung_nhan'],
            'file_minh_chung' => $validated['file_minh_chung'],
            'ngay_cap'        => $validated['ngay_cap'],
            'ngay_het_han'    => $validated['ngay_het_han'] ?? null,
            'created_at'      => now(),
        ]);

        return $this->success($cert->load('uploader:id,ho_ten'), 'Đã thêm chứng nhận kiểm định.', 201);
    }

    public function destroy(int $batchId, int $id): JsonResponse
    {
        $cert = Certificate::where('batch_id', $batchId)->find($id);
        if ($cert === null) {
            return $this->error('Không tìm thấy chứng nhận.', 404);
        }

        $cert->delete();

        return $this->success(null, 'Đã xoá chứng nhận.');
    }
}

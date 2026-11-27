<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\FarmingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FarmingLogController extends Controller
{
    use ApiResponse;

    public function index(int $batchId): JsonResponse
    {
        $batch = Batch::with('product:id,ten_san_pham')->find($batchId);
        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $logs = FarmingLog::query()
            ->with('farmStaff:id,ho_ten')
            ->where('batch_id', $batchId)
            ->orderBy('thoi_gian')
            ->orderBy('id')
            ->get();

        return $this->success([
            'batch' => $batch,
            'logs'  => $logs,
        ], 'Lấy nhật ký canh tác thành công.');
    }

    public function store(Request $request, int $batchId): JsonResponse
    {
        $batch = Batch::find($batchId);
        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $validated = $request->validate([
            'hanh_dong'           => ['required', 'string', 'max:100'],
            'thoi_gian'           => ['required', 'date', 'before_or_equal:now'],
            'mo_ta'               => ['nullable', 'string', 'max:2000'],
            'hinh_anh_minh_chung' => ['nullable', 'url', 'max:255'],
        ], [
            'hanh_dong.required'      => 'Vui lòng nhập loại hoạt động canh tác.',
            'thoi_gian.required'      => 'Vui lòng nhập thời gian thực hiện.',
            'hinh_anh_minh_chung.url' => 'Đường dẫn ảnh/video minh chứng không hợp lệ.',
        ]);

        $log = FarmingLog::create([
            'batch_id'            => $batchId,
            'farm_staff_id'       => Auth::guard('api')->id(),
            'hanh_dong'           => $validated['hanh_dong'],
            'mo_ta'               => $validated['mo_ta'] ?? null,
            'hinh_anh_minh_chung' => $validated['hinh_anh_minh_chung'] ?? null,
            'thoi_gian'           => $validated['thoi_gian'],
        ]);

        return $this->success($log->load('farmStaff:id,ho_ten'), 'Đã thêm nhật ký canh tác.', 201);
    }

    public function destroy(int $batchId, int $logId): JsonResponse
    {
        $log = FarmingLog::where('batch_id', $batchId)->find($logId);
        if ($log === null) {
            return $this->error('Không tìm thấy nhật ký.', 404);
        }

        if ($log->created_at !== null && $log->created_at->copy()->addHours(24)->isPast()) {
            return $this->error('Sau 24 giờ, nhật ký canh tác đã công bố không được phép xóa.', 422);
        }

        $log->delete();

        return $this->success(null, 'Đã xoá nhật ký canh tác.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\OrderDetail;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ComplaintService $complaintService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_detail_id' => ['required', 'integer', 'exists:order_details,id'],
            'ly_do'           => ['required', 'string', 'max:255'],
            'noi_dung'        => ['nullable', 'string', 'max:2000'],
            'minh_chung'      => ['required', 'string', 'max:255'],
        ], [
            'order_detail_id.required' => 'Vui lòng chọn sản phẩm trong đơn cần khiếu nại.',
            'ly_do.required'           => 'Vui lòng nhập lý do khiếu nại.',
            'minh_chung.required'      => 'Vui lòng đính kèm ảnh hoặc video minh chứng.',
        ]);

        $detail = OrderDetail::findOrFail($validated['order_detail_id']);

        $complaint = $this->complaintService->createComplaint(
            Auth::guard('api')->user(),
            $detail,
            $validated['ly_do'],
            $validated['noi_dung'] ?? null,
            $validated['minh_chung'],
        );

        return $this->success($complaint, 'Đã gửi yêu cầu khiếu nại.', 201);
    }

    public function myComplaints(): JsonResponse
    {
        $complaints = Complaint::query()
            ->with(['orderDetail.order:id,ma_don,tong_tien', 'orderDetail.product:id,ten_san_pham', 'orderDetail.batch:id,ma_lo'])
            ->where('user_id', Auth::guard('api')->id())
            ->latest()
            ->get();

        return $this->success($complaints, 'Lấy danh sách khiếu nại thành công.');
    }

    public function index(Request $request): JsonResponse
    {
        $complaints = Complaint::query()
            ->with([
                'orderDetail.order:id,ma_don,tong_tien,ngay_cap_nhat',
                'orderDetail.product:id,ten_san_pham,don_vi_tinh',
                'orderDetail.batch:id,ma_lo',
                'customer:id,ho_ten,email,so_dien_thoai',
            ])
            ->when($request->filled('trang_thai'), fn ($q) => $q->where('trang_thai', $request->input('trang_thai')))
            ->latest()
            ->paginate(max(1, (int) $request->input('per_page', 15)));

        return $this->paginated($complaints, 'Lấy danh sách khiếu nại thành công.');
    }

    public function handle(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'huong_xu_ly'  => ['required', 'in:hoan_tien,giao_bu,tu_choi'],
            'so_tien_hoan' => ['nullable', 'numeric', 'min:0'],
        ], [
            'huong_xu_ly.required' => 'Vui lòng chọn hướng xử lý khiếu nại.',
        ]);

        $complaint = Complaint::find($id);
        if ($complaint === null) {
            return $this->error('Không tìm thấy khiếu nại.', 404);
        }

        $complaint = $this->complaintService->handle(
            $complaint,
            Auth::guard('api')->user(),
            $validated['huong_xu_ly'],
            (float) ($validated['so_tien_hoan'] ?? 0),
        );

        return $this->success($complaint, 'Đã xử lý khiếu nại thành công.');
    }
}

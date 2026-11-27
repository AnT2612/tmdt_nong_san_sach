<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\VietQRService;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly VNPayService $vnPayService,
        private readonly VietQRService $vietQrService,
    ) {
    }

    // Đặt hàng
    // Không bắt buộc đăng nhập => khách vãng lai (Guest) vẫn mua được
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_name'    => ['required', 'string', 'max:100'],
            'receiver_phone'   => ['required', 'string', 'max:20'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'note'             => ['nullable', 'string', 'max:500'],
            'payment_method'   => ['required', 'in:cod,vnpay,vietqr'],
            'points_used'      => ['nullable', 'integer', 'min:0'],

            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.001'],
        ], [
            'receiver_name.required'    => 'Vui lòng nhập tên người nhận.',
            'receiver_phone.required'   => 'Vui lòng nhập số điện thoại.',
            'shipping_address.required' => 'Vui lòng nhập địa chỉ giao hàng.',
            'payment_method.required'   => 'Vui lòng chọn phương thức thanh toán.',
            'items.required'            => 'Giỏ hàng đang trống.',
            'items.min'                 => 'Giỏ hàng phải có ít nhất một sản phẩm.',
        ]);

        /** @var \App\Models\User|null $customer */
        $customer = Auth::guard('api')->user();

        $order = $this->orderService->placeOrder($validated, $customer);

        $response = [
            'order' => $order->load('items'),
        ];

        // Nếu chọn VNPay thì tạo luôn đường dẫn thanh toán để Frontend chuyển trang.
        if ($order->payment_method === Order::PAYMENT_VNPAY) {
            $response['payment_url'] = $this->vnPayService->createPaymentUrl(
                $order,
                $request->ip() ?? '127.0.0.1',
            );
        }

        // Nếu chọn VietQR thì tạo payload mã QR thanh toán để Frontend hiển thị quét mã tức thì.
        if ($order->payment_method === Order::PAYMENT_VIETQR) {
            $response['vietqr'] = $this->vietQrService->getVietQrPayload($order);
        }

        return $this->success($response, 'Đặt hàng thành công. Vui lòng hoàn tất thanh toán hoặc chờ duyệt đơn.', 201);
    }

    // Danh sách đơn của tôi
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with([
                'items',
                'batchAllocations.batch:id,batch_code,product_id,harvest_date,expiry_date,traceability_token',
                'batchAllocations.batch.product:id,name,unit,thumbnail_url',
            ])
            ->withCount('items')
            ->where('user_id', Auth::guard('api')->id())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(max(1, (int) $request->input('per_page', 10)));

        return $this->paginated($orders, 'Lấy danh sách đơn hàng thành công.');
    }

    // Chi tiết đơn hàng
    // Bao gồm cả các dòng phân bổ lô - đây là bằng chứng FEFO và chứa
    // token để khách truy xuất nguồn gốc từng lô hàng đã nhận
    public function show(int $id): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $query = Order::query()
            ->with([
                'items',
                'batchAllocations.batch:id,batch_code,product_id,harvest_date,expiry_date,traceability_token',
                'batchAllocations.batch.product:id,name,unit,thumbnail_url',
                'payments',
                'complaint',
            ]);

        // Nếu khách đăng nhập: chỉ xem đơn của mình (hoặc nhân viên có quyền)
        if ($user !== null) {
            if (! in_array($user->role, ['sales', 'admin', 'warehouse_staff'], true)) {
                $query->where('user_id', $user->id);
            }
        } else {
            // Khách vãng lai: chỉ xem đơn không có tài khoản (user_id is null)
            $query->whereNull('user_id');
        }

        $order = $query->find($id);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        // Bổ sung thông tin VietQR nếu đơn chọn phương thức VietQR
        if ($order->payment_method === Order::PAYMENT_VIETQR) {
            $order->setAttribute('vietqr', $this->vietQrService->getVietQrPayload($order));
        }

        return $this->success($order, 'Lấy chi tiết đơn hàng thành công.');
    }

    // Mô phỏng sales duyệt đơn (Dành cho demo đồ án)
    // Tự động chạy thuật toán FEFO phân bổ lô hàng để xem ngay mã QR truy xuất
    public function demoConfirm(int $id): JsonResponse
    {
        $order = Order::find($id);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return $this->error('Đơn hàng này đã được duyệt hoặc đã xử lý trước đó.', 422);
        }

        // Tìm nhân viên sales hoặc admin đại diện duyệt đơn
        $sales = \App\Models\User::where('role', 'sales')->first()
            ?? \App\Models\User::where('role', 'admin')->first()
            ?? Auth::guard('api')->user();

        if ($sales === null) {
            return $this->error('Không tìm thấy tài khoản nhân viên để duyệt đơn.', 422);
        }

        $order = $this->orderService->confirmOrder($order, $sales);

        return $this->success(
            $order->fresh([
                'items',
                'batchAllocations.batch:id,batch_code,product_id,harvest_date,expiry_date,traceability_token',
                'batchAllocations.batch.product:id,name,unit,thumbnail_url',
                'payments',
                'complaint',
            ]),
            'Mô phỏng Sales duyệt đơn thành công! Đã tự động phân bổ lô theo FEFO.'
        );
    }

    // Khách tự huỷ đơn
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Vui lòng cho biết lý do huỷ đơn.',
        ]);

        $order = Order::where('user_id', Auth::guard('api')->id())->find($id);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        if (! $order->canBeCancelled()) {
            return $this->error('Đơn hàng đã được duyệt nên không thể tự huỷ. Vui lòng liên hệ CSKH.', 422);
        }

        $order = $this->orderService->cancelOrder($order, Auth::guard('api')->user(), $validated['reason']);

        return $this->success($order, 'Đã huỷ đơn hàng.');
    }
}

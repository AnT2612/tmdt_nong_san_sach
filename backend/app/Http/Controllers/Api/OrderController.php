<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\ShippingService;
use App\Services\VietQRService;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly ShippingService $shippingService,
        private readonly VNPayService $vnPayService,
        private readonly VietQRService $vietQrService,
    ) {
    }

    public function shippingFee(Request $request): JsonResponse
    {
        $diaChi = (string) $request->input('dia_chi_giao', '');
        $subtotal = (float) $request->input('tam_tinh', 0);
        $loaiGiao = (string) $request->input('loai_giao', Order::LOAI_GIAO_THUONG);
        $lat = $request->filled('vi_do') ? (float) $request->input('vi_do') : null;
        $lng = $request->filled('kinh_do') ? (float) $request->input('kinh_do') : null;

        return $this->success(
            $this->shippingService->calculate($diaChi, $subtotal, $loaiGiao, $lat, $lng),
            'Tính phí vận chuyển thành công.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ten_nguoi_nhan'   => ['required', 'string', 'max:100'],
            'so_dien_thoai'    => ['required', 'string', 'max:15'],
            'email_nguoi_nhan' => ['nullable', 'email', 'max:150'],
            'dia_chi_giao'     => ['required', 'string', 'max:255'],
            'loai_giao'        => ['nullable', 'in:thuong,hoa_toc'],
            'cong_thanh_toan'  => ['required', 'in:cod,momo,vnpay,vietqr'],
            'diem_su_dung'     => ['nullable', 'integer', 'min:0'],
            'vi_do'            => ['nullable', 'numeric'],
            'kinh_do'          => ['nullable', 'numeric'],

            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.01'],
        ], [
            'ten_nguoi_nhan.required'  => 'Vui lòng nhập tên người nhận.',
            'so_dien_thoai.required'   => 'Vui lòng nhập số điện thoại.',
            'dia_chi_giao.required'    => 'Vui lòng nhập địa chỉ giao hàng.',
            'cong_thanh_toan.required' => 'Vui lòng chọn cổng thanh toán.',
            'items.required'           => 'Giỏ hàng đang trống.',
        ]);

        $customer = Auth::guard('api')->user();
        $order = $this->orderService->placeOrder($validated, $customer);

        $response = [
            'order' => $order->load(['orderDetails.product', 'orderDetails.batch', 'payments']),
        ];

        if (($validated['cong_thanh_toan'] ?? 'cod') === 'vnpay') {
            $response['payment_url'] = $this->vnPayService->createPaymentUrl(
                $order,
                $request->ip() ?? '127.0.0.1',
            );
        }

        if (($validated['cong_thanh_toan'] ?? 'cod') === 'vietqr') {
            $response['vietqr'] = $this->vietQrService->getVietQrPayload($order);
        }

        return $this->success($response, 'Đặt hàng thành công.', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with([
                'orderDetails.product:id,ten_san_pham,don_vi_tinh,hinh_anh_dai_dien',
                'orderDetails.batch:id,ma_lo,ngay_thu_hoach,ngay_het_han,qr_code',
                'payments',
            ])
            ->withCount('orderDetails')
            ->where('user_id', Auth::guard('api')->id())
            ->when($request->filled('trang_thai'), fn ($q) => $q->where('trang_thai', $request->input('trang_thai')))
            ->latest('ngay_tao')
            ->paginate(max(1, (int) $request->input('per_page', 10)));

        return $this->paginated($orders, 'Lấy danh sách đơn hàng thành công.');
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::guard('api')->user();
        $user?->loadMissing('role');

        $query = Order::query()->with([
            'orderDetails.product:id,ten_san_pham,don_vi_tinh,hinh_anh_dai_dien',
            'orderDetails.batch:id,ma_lo,ngay_thu_hoach,ngay_het_han,qr_code',
            'orderDetails.complaints',
            'payments',
            'sourceComplaint',
        ]);

        if ($user !== null) {
            if (! $user->hasRole('sales', 'admin', 'farm_staff')) {
                $query->where('user_id', $user->id);
            }
        } else {
            $query->whereNull('user_id');
        }

        $order = $query->find($id);
        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        $latestPayment = $order->payments->first();
        if ($latestPayment?->cong_thanh_toan === 'vietqr') {
            $order->setAttribute('vietqr', $this->vietQrService->getVietQrPayload($order));
        }

        return $this->success($order, 'Lấy chi tiết đơn hàng thành công.');
    }

    public function demoConfirm(int $id): JsonResponse
    {
        $order = Order::find($id);
        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        $order = $this->orderService->confirmOrder($order);

        return $this->success(
            $order->load(['orderDetails.product', 'orderDetails.batch', 'payments']),
            'Duyệt đơn và phân bổ lô FEFO thành công!'
        );
    }

    public function cancel(int $id): JsonResponse
    {
        $order = Order::where('user_id', Auth::guard('api')->id())->find($id);
        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        if (! $order->canBeCancelled()) {
            return $this->error('Đơn hàng đã được duyệt nên không thể tự huỷ.', 422);
        }

        $order = $this->orderService->cancelOrder($order);

        return $this->success($order, 'Đã huỷ đơn hàng.');
    }
}

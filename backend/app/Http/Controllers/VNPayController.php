<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VNPayController extends Controller
{
    public function __construct(
        private readonly VNPayService $vnPayService,
    ) {
    }

    // Tạo URL thanh toán VNPay
    // POST /api/vnpay/create
    //
    // Nhận order_id, kiểm tra trạng thái và tính toán số tiền hoàn toàn từ Backend
    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ], [
            'order_id.required' => 'Vui lòng chọn đơn hàng cần thanh toán.',
            'order_id.exists'   => 'Đơn hàng không tồn tại trên hệ thống.',
        ]);

        $order = Order::with('items')->findOrFail($validated['order_id']);

        // 1. Kiểm tra đơn hàng đã thanh toán chưa
        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng này đã được thanh toán thành công trước đó.',
            ], 422);
        }

        // 2. Kiểm tra đơn hàng có bị huỷ không
        if ($order->status === Order::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng này đã bị huỷ, không thể thanh toán.',
            ], 422);
        }

        // 3. Đảm bảo cấu hình VNPay
        if (! $this->vnPayService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống chưa được cấu hình thông tin VNPay (TMN Code hoặc Hash Secret).',
            ], 500);
        }

        try {
            $paymentUrl = $this->vnPayService->createPaymentUrl(
                $order,
                $request->ip() ?? '127.0.0.1'
            );

            return response()->json([
                'success'      => true,
                'payment_url'  => $paymentUrl,
                'order_id'     => $order->id,
                'order_code'   => $order->order_code,
                'total_amount' => (int) $order->total_amount,
                'message'      => 'Tạo URL thanh toán VNPay thành công.',
            ]);
        } catch (\Throwable $exception) {
            Log::error('Lỗi tạo URL thanh toán VNPay: ' . $exception->getMessage(), [
                'order_id' => $order->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo đường dẫn thanh toán: ' . $exception->getMessage(),
            ], 500);
        }
    }

    // Xử lý return URL khi khách thanh toán xong
    // GET /api/vnpay/return
    //
    // Xác thực chữ ký, cập nhật trạng thái đơn và chuyển khách về trang kết quả đơn hàng
    public function returnPayment(Request $request): RedirectResponse
    {
        $payload = $request->query();
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        $result = $this->vnPayService->handleReturn($payload);
        $status = $result['status'];
        $code   = $result['code'];
        $txn    = $result['transaction'] ?? '';

        if ($result['order'] !== null) {
            $orderId = $result['order']->id;
            return redirect()->away(
                "{$frontendUrl}/don-hang/{$orderId}?payment={$status}&vnp_code={$code}&vnp_txn={$txn}"
            );
        }

        // Nếu không tìm thấy đơn hoặc chữ ký giả mạo
        $error = $result['error'] ?? 'unknown_error';
        return redirect()->away(
            "{$frontendUrl}/don-hang?payment=failed&vnp_code={$code}&error={$error}"
        );
    }

    // Xử lý IPN webhook từ máy chủ VNPay (Server-to-Server)
    // GET /api/vnpay/ipn
    //
    // VNPay gọi trực tiếp vào đây. Trả về đúng định dạng JSON theo tài liệu VNPay.
    public function ipn(Request $request): JsonResponse
    {
        $payload = $request->query();

        $response = $this->vnPayService->handleIpn($payload);

        return response()->json($response);
    }
}

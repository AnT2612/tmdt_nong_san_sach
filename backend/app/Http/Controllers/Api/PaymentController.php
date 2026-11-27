<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly VNPayService $vnPayService,
    ) {
    }

    // Tạo đường dẫn thanh toán cho một đơn
    public function createUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ], [
            'order_id.required' => 'Vui lòng chọn đơn hàng cần thanh toán.',
            'order_id.exists'   => 'Đơn hàng không tồn tại.',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if ($order->payment_status === 'paid') {
            return $this->error('Đơn hàng này đã được thanh toán.', 422);
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            return $this->error('Đơn hàng này đã bị huỷ, không thể thanh toán.', 422);
        }

        try {
            $paymentUrl = $this->vnPayService->createPaymentUrl(
                $order,
                $request->ip() ?? '127.0.0.1',
            );

            return $this->success([
                'payment_url'   => $paymentUrl,
                'order_id'      => $order->id,
                'order_code'    => $order->order_code,
                'total_amount'  => (int) $order->total_amount,
                'is_simulation' => ! $this->vnPayService->isConfigured(),
            ], 'Tạo đường dẫn thanh toán thành công.');
        } catch (\Throwable $e) {
            return $this->error('Không thể tạo URL thanh toán: ' . $e->getMessage(), 500);
        }
    }

    // VNPay gọi về sau khi khách thanh toán (Trình duyệt)
    public function returnUrl(Request $request): RedirectResponse
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

        $error = $result['error'] ?? 'unknown_error';
        return redirect()->away(
            "{$frontendUrl}/don-hang?payment=failed&vnp_code={$code}&error={$error}"
        );
    }

    // VNPay gọi IPN webhook (Server-to-Server)
    public function ipn(Request $request): JsonResponse
    {
        $payload = $request->query();
        $response = $this->vnPayService->handleIpn($payload);

        return response()->json($response);
    }

    // Xác nhận thanh toán QR trực tiếp từ modal frontend
    // Dùng cho sinh viên demo quét mã VietQR / MoMo / VNPay ngay trên giao diện
    public function confirmPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if ($order->payment_status !== 'paid') {
            $order->payment_status = 'paid';
            $order->save();

            $order->payments()->create([
                'method'            => $order->payment_method ?? Order::PAYMENT_VIETQR,
                'amount'            => $order->total_amount,
                'status'            => 'success',
                'paid_at'           => now(),
                'vnp_response_code' => '00',
                'raw_response'      => ['note' => 'Thanh toán quét mã QR (VietQR / VNPay / MoMo) thành công.'],
            ]);
        }

        // Tự động duyệt đơn và phân bổ lô hàng theo FEFO nếu đơn vẫn đang chờ xử lý
        if ($order->status === Order::STATUS_PENDING) {
            $sales = \App\Models\User::where('role', 'sales')->first()
                ?? \App\Models\User::where('role', 'admin')->first()
                ?? Auth::guard('api')->user();

            if ($sales !== null) {
                try {
                    $order = app(\App\Services\OrderService::class)->confirmOrder($order, $sales);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Không thể auto-confirm khi xác nhận thanh toán: ' . $e->getMessage());
                }
            }
        }

        return $this->success([
            'order' => $order->fresh([
                'items',
                'batchAllocations.batch:id,batch_code,product_id,harvest_date,expiry_date,traceability_token',
                'batchAllocations.batch.product:id,name,unit,thumbnail_url',
            ]),
        ], 'Thanh toán quét mã QR thành công và đã phân bổ lô hàng.');
    }

    // Webhook tự động xác minh biến động số dư tài khoản ngân hàng (SePay / VietQR / Casso)
    // Khi tiền vào VietinBank với nội dung NSS + Mã đơn, Webhook gửi về đây,
    // hệ thống tự động đối soát số tiền và mã đơn, đổi trạng thái sang PAID và phân bổ lô FEFO ngay lập tức.
    public function sepayWebhook(Request $request): JsonResponse
    {
        $content = (string) ($request->input('content') ?? $request->input('description') ?? $request->input('order_code') ?? '');
        $amountIn = (float) ($request->input('transferAmount') ?? $request->input('amount') ?? 0);

        \Illuminate\Support\Facades\Log::info('Ngân hàng VietinBank Webhook nhận được:', [
            'content' => $content,
            'amount'  => $amountIn,
            'payload' => $request->all(),
        ]);

        // Tìm đơn hàng theo mã đơn trong nội dung chuyển khoản
        $order = null;
        if (preg_match('/ORD[-]?\d{8}[-]?\d{4}/i', $content, $matches)) {
            $codeClean = str_replace('-', '', $matches[0]);
            $order = Order::all()->first(fn ($o) => str_replace('-', '', $o->order_code) === $codeClean);
        }

        if ($order === null && $request->filled('order_id')) {
            $order = Order::find($request->input('order_id'));
        }

        if ($order === null) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng tương ứng với nội dung chuyển khoản.',
            ], 404);
        }

        if ($amountIn > 0 && $amountIn < ($order->total_amount - 1000)) {
            return response()->json([
                'success' => false,
                'message' => 'Số tiền chuyển không đủ giá trị đơn hàng.',
            ], 422);
        }

        if ($order->payment_status !== 'paid') {
            $order->payment_status = 'paid';
            $order->save();

            $order->payments()->create([
                'method'            => Order::PAYMENT_VIETQR,
                'amount'            => $order->total_amount,
                'status'            => 'success',
                'paid_at'           => now(),
                'vnp_response_code' => '00',
                'raw_response'      => $request->all(),
            ]);
        }

        if ($order->status === Order::STATUS_PENDING) {
            $sales = \App\Models\User::where('role', 'sales')->first()
                ?? \App\Models\User::where('role', 'admin')->first()
                ?? \App\Models\User::first();

            if ($sales !== null) {
                try {
                    app(\App\Services\OrderService::class)->confirmOrder($order, $sales);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Lỗi auto-confirm qua Webhook VietQR: ' . $e->getMessage());
                }
            }
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Xác minh thanh toán ngân hàng thành công!',
            'order_id'   => $order->id,
            'order_code' => $order->order_code,
        ]);
    }
}

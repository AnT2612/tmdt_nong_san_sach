<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly VNPayService $vnPayService,
        private readonly OrderService $orderService,
    ) {
    }

    public function createUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if ($order->isPaid()) {
            return $this->error('Đơn hàng này đã được thanh toán.', 422);
        }

        if ($order->trang_thai === Order::STATUS_DA_HUY) {
            return $this->error('Đơn hàng này đã bị huỷ.', 422);
        }

        $paymentUrl = $this->vnPayService->createPaymentUrl($order, $request->ip() ?? '127.0.0.1');

        return $this->success([
            'payment_url'   => $paymentUrl,
            'order_id'      => $order->id,
            'ma_don'        => $order->ma_don,
            'tong_tien'     => (int) $order->tong_tien,
            'is_simulation' => ! $this->vnPayService->isConfigured(),
        ], 'Tạo đường dẫn thanh toán thành công.');
    }

    public function returnUrl(Request $request): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $result = $this->vnPayService->handleReturn($request->query());

        if ($result['order'] !== null) {
            $orderId = $result['order']->id;
            return redirect()->away(
                "{$frontendUrl}/don-hang/{$orderId}?payment={$result['status']}&vnp_code={$result['code']}&vnp_txn=" . ($result['transaction'] ?? '')
            );
        }

        return redirect()->away(
            "{$frontendUrl}/don-hang?payment=failed&vnp_code={$result['code']}&error=" . ($result['error'] ?? 'unknown_error')
        );
    }

    public function ipn(Request $request): JsonResponse
    {
        return response()->json($this->vnPayService->handleIpn($request->query()));
    }

    public function confirmPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::with('payments')->findOrFail($validated['order_id']);

        $payment = $order->payments()->latest('created_at')->first();
        if ($payment !== null) {
            $payment->trang_thai = Payment::STATUS_THANH_CONG;
            $payment->save();
        } else {
            Payment::create([
                'order_id'        => $order->id,
                'cong_thanh_toan' => Payment::CONG_VIETQR,
                'so_tien'         => $order->tong_tien,
                'trang_thai'      => Payment::STATUS_THANH_CONG,
                'created_at'      => now(),
            ]);
        }

        if ($order->trang_thai === Order::STATUS_CHO_DUYET) {
            try {
                $order = $this->orderService->confirmOrder($order);
            } catch (\Throwable $e) {
                Log::warning('Auto-confirm sau xác nhận thanh toán: ' . $e->getMessage());
            }
        }

        return $this->success([
            'order' => $order->fresh(['orderDetails.product', 'orderDetails.batch', 'payments']),
        ], 'Xác nhận thanh toán thành công và đã phân bổ lô FEFO.');
    }

    public function sepayWebhook(Request $request): JsonResponse
    {
        $content  = (string) ($request->input('content') ?? $request->input('description') ?? $request->input('ma_don') ?? '');
        $amountIn = (float) ($request->input('transferAmount') ?? $request->input('amount') ?? 0);

        $order = null;
        if (preg_match('/ORD[-]?\d{8}[-]?\d{4}/i', $content, $matches)) {
            $codeClean = str_replace('-', '', $matches[0]);
            $order = Order::all()->first(fn ($o) => str_replace('-', '', $o->ma_don) === $codeClean);
        }

        if ($order === null && $request->filled('order_id')) {
            $order = Order::find($request->input('order_id'));
        }

        if ($order === null) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        if ($amountIn > 0 && $amountIn < ($order->tong_tien - 1000)) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Số tiền chuyển không đủ.'], 422);
        }

        $payment = $order->payments()->latest('created_at')->first();
        if ($payment !== null) {
            $payment->trang_thai = Payment::STATUS_THANH_CONG;
            $payment->save();
        } else {
            Payment::create([
                'order_id'        => $order->id,
                'cong_thanh_toan' => Payment::CONG_VIETQR,
                'so_tien'         => $order->tong_tien,
                'trang_thai'      => Payment::STATUS_THANH_CONG,
                'created_at'      => now(),
            ]);
        }

        if ($order->trang_thai === Order::STATUS_CHO_DUYET) {
            try {
                $this->orderService->confirmOrder($order);
            } catch (\Throwable $e) {
                Log::warning('Auto-confirm webhook VietQR: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status'   => 'success',
            'success'  => true,
            'message'  => 'Xác minh thanh toán thành công.',
            'order_id' => $order->id,
            'ma_don'   => $order->ma_don,
        ]);
    }
}

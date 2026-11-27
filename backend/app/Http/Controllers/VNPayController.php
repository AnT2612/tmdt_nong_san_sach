<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VNPayController extends Controller
{
    public function __construct(
        private readonly VNPayService $vnPayService,
    ) {
    }

    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if ($order->isPaid()) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Đơn hàng đã thanh toán.'], 422);
        }

        if ($order->trang_thai === Order::STATUS_DA_HUY) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Đơn hàng đã bị huỷ.'], 422);
        }

        if (! $this->vnPayService->isConfigured()) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Chưa cấu hình VNPay.'], 500);
        }

        $paymentUrl = $this->vnPayService->createPaymentUrl($order, $request->ip() ?? '127.0.0.1');

        return response()->json([
            'status'      => 'success',
            'success'     => true,
            'payment_url' => $paymentUrl,
            'order_id'    => $order->id,
            'ma_don'      => $order->ma_don,
            'tong_tien'   => (int) $order->tong_tien,
            'message'     => 'Tạo URL thanh toán VNPay thành công.',
        ]);
    }

    public function returnPayment(Request $request): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $result = $this->vnPayService->handleReturn($request->query());

        if ($result['order'] !== null) {
            return redirect()->away(
                "{$frontendUrl}/don-hang/{$result['order']->id}?payment={$result['status']}&vnp_code={$result['code']}&vnp_txn=" . ($result['transaction'] ?? '')
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
}

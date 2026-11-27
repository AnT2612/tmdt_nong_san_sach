<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VNPayService
{
    public function isConfigured(): bool
    {
        return ! empty(config('vnpay.tmn_code')) && ! empty(config('vnpay.hash_secret'));
    }

    public function createPaymentUrl(Order $order, string $clientIp): string
    {
        if ($order->isPaid()) {
            throw new \InvalidArgumentException('Đơn hàng này đã được thanh toán trước đó.');
        }

        $vnpTmnCode    = (string) config('vnpay.tmn_code');
        $vnpHashSecret = (string) config('vnpay.hash_secret');
        $vnpUrl        = (string) config('vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $vnpReturnUrl  = (string) config('vnpay.return_url');

        $cleanIp = ($clientIp === '::1' || empty($clientIp)) ? '127.0.0.1' : $clientIp;
        $txnRef  = 'ORDER' . $order->id . '_' . time();
        $now     = Carbon::now('Asia/Ho_Chi_Minh');

        $inputData = [
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $vnpTmnCode,
            'vnp_Amount'     => (int) ($order->tong_tien * 100),
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_CurrCode'   => 'VND',
            'vnp_IpAddr'     => $cleanIp,
            'vnp_Locale'     => 'vn',
            'vnp_OrderInfo'  => 'Thanh toan don hang ' . $order->ma_don,
            'vnp_OrderType'  => 'other',
            'vnp_ReturnUrl'  => $vnpReturnUrl,
            'vnp_TxnRef'     => $txnRef,
            'vnp_ExpireDate' => $now->copy()->addMinutes(15)->format('YmdHis'),
        ];

        ksort($inputData);

        $query    = '';
        $hashData = '';
        $i        = 0;

        foreach ($inputData as $key => $value) {
            if ($i === 1) {
                $hashData .= '&' . urlencode($key) . '=' . urlencode((string) $value);
            } else {
                $hashData .= urlencode($key) . '=' . urlencode((string) $value);
                $i = 1;
            }
            $query .= urlencode($key) . '=' . urlencode((string) $value) . '&';
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnpHashSecret);
        $paymentUrl = rtrim($vnpUrl, '?') . '?' . $query . 'vnp_SecureHash=' . $secureHash;

        Payment::create([
            'order_id'        => $order->id,
            'cong_thanh_toan' => Payment::CONG_VNPAY,
            'ma_giao_dich'    => $txnRef,
            'so_tien'         => $order->tong_tien,
            'trang_thai'      => Payment::STATUS_CHO,
            'created_at'      => now(),
        ]);

        return $paymentUrl;
    }

    public function verifySignature(array $payload): bool
    {
        $vnpHashSecret = (string) config('vnpay.hash_secret');
        $receivedHash  = (string) ($payload['vnp_SecureHash'] ?? '');

        if ($vnpHashSecret === '' || $receivedHash === '') {
            return false;
        }

        $inputData = [];
        foreach ($payload as $key => $value) {
            if (str_starts_with($key, 'vnp_') && $key !== 'vnp_SecureHash' && $key !== 'vnp_SecureHashType') {
                $inputData[$key] = $value;
            }
        }

        ksort($inputData);

        $hashData = '';
        $i        = 0;
        foreach ($inputData as $key => $value) {
            if ($i === 1) {
                $hashData .= '&' . urlencode($key) . '=' . urlencode((string) $value);
            } else {
                $hashData .= urlencode($key) . '=' . urlencode((string) $value);
                $i = 1;
            }
        }

        $calculatedHash = hash_hmac('sha512', $hashData, $vnpHashSecret);

        return hash_equals(strtolower($calculatedHash), strtolower($receivedHash));
    }

    public function handleReturn(array $payload): array
    {
        if (! $this->verifySignature($payload)) {
            return [
                'success'     => false,
                'order'       => null,
                'status'      => 'failed',
                'code'        => (string) ($payload['vnp_ResponseCode'] ?? '97'),
                'transaction' => null,
                'error'       => 'invalid_signature',
            ];
        }

        $txnRef  = (string) ($payload['vnp_TxnRef'] ?? '');
        $payment = Payment::where('ma_giao_dich', $txnRef)->first();
        $order   = $payment?->order;

        if ($payment === null || $order === null) {
            return [
                'success'     => false,
                'order'       => null,
                'status'      => 'failed',
                'code'        => (string) ($payload['vnp_ResponseCode'] ?? '01'),
                'transaction' => null,
                'error'       => 'order_not_found',
            ];
        }

        $responseCode  = (string) ($payload['vnp_ResponseCode'] ?? '99');
        $transactionNo = $payload['vnp_TransactionNo'] ?? $txnRef;
        $isSuccess     = $responseCode === '00';
        $isCancelled   = $responseCode === '24';

        DB::transaction(function () use ($payment, $order, $isSuccess) {
            $payment->trang_thai = $isSuccess ? Payment::STATUS_THANH_CONG : Payment::STATUS_THAT_BAI;
            $payment->save();

            if ($isSuccess && $order->trang_thai === Order::STATUS_CHO_DUYET) {
                try {
                    app(OrderService::class)->confirmOrder($order);
                } catch (\Throwable $e) {
                    Log::warning('Không thể tự động duyệt đơn sau VNPay: ' . $e->getMessage());
                }
            }
        });

        return [
            'success'     => $isSuccess,
            'order'       => $order->fresh(),
            'status'      => $isSuccess ? 'success' : ($isCancelled ? 'cancelled' : 'failed'),
            'code'        => $responseCode,
            'transaction' => $transactionNo,
            'error'       => $isSuccess ? null : ($isCancelled ? 'customer_cancelled' : 'transaction_failed'),
        ];
    }

    public function handleIpn(array $payload): array
    {
        if (! $this->verifySignature($payload)) {
            return ['RspCode' => '97', 'Message' => 'Invalid signature'];
        }

        $txnRef  = (string) ($payload['vnp_TxnRef'] ?? '');
        $payment = Payment::where('ma_giao_dich', $txnRef)->first();

        if ($payment === null || $payment->order === null) {
            return ['RspCode' => '01', 'Message' => 'Order not found'];
        }

        if ($payment->trang_thai === Payment::STATUS_THANH_CONG) {
            return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
        }

        $expectedAmount = (int) ($payment->order->tong_tien * 100);
        if ($expectedAmount !== (int) ($payload['vnp_Amount'] ?? 0)) {
            return ['RspCode' => '04', 'Message' => 'Invalid amount'];
        }

        $isSuccess = ((string) ($payload['vnp_ResponseCode'] ?? '')) === '00';

        DB::transaction(function () use ($payment, $isSuccess) {
            $payment->trang_thai = $isSuccess ? Payment::STATUS_THANH_CONG : Payment::STATUS_THAT_BAI;
            $payment->save();

            if ($isSuccess && $payment->order->trang_thai === Order::STATUS_CHO_DUYET) {
                try {
                    app(OrderService::class)->confirmOrder($payment->order);
                } catch (\Throwable $e) {
                    Log::warning('Không thể tự động duyệt đơn qua IPN: ' . $e->getMessage());
                }
            }
        });

        return ['RspCode' => '00', 'Message' => 'Confirm Success'];
    }
}

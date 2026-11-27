<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VNPayService
{
    // Kiểm tra cấu hình VNPay có đầy đủ trong file cấu hình hay chưa
    public function isConfigured(): bool
    {
        return ! empty(config('vnpay.tmn_code')) && ! empty(config('vnpay.hash_secret'));
    }

    // Tạo đường dẫn thanh toán VNPay
    public function createPaymentUrl(Order $order, string $clientIp): string
    {
        if ($order->payment_status === 'paid') {
            throw new \InvalidArgumentException('Đơn hàng này đã được thanh toán trước đó.');
        }

        $vnpTmnCode    = config('vnpay.tmn_code');
        $vnpHashSecret = config('vnpay.hash_secret');
        $vnpUrl        = config('vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $vnpReturnUrl  = config('vnpay.return_url');

        // Chuẩn hóa địa chỉ IP (tránh IPv6 localhost '::1' gây lỗi trên cổng)
        $cleanIp = ($clientIp === '::1' || empty($clientIp)) ? '127.0.0.1' : $clientIp;

        // Sinh mã tham chiếu duy nhất cho mỗi lượt gửi sang VNPay
        // Định dạng ORDER{id}_{timestamp} giúp tránh lỗi "Trùng mã giao dịch" khi khách thử thanh toán lại
        $txnRef = 'ORDER' . $order->id . '_' . time();

        // Múi giờ Việt Nam theo yêu cầu của VNPay
        $now = Carbon::now('Asia/Ho_Chi_Minh');

        $inputData = [
            'vnp_Version'        => '2.1.0',
            'vnp_Command'        => 'pay',
            'vnp_TmnCode'        => $vnpTmnCode,
            'vnp_Amount'         => (int) ($order->total_amount * 100), // VNPay tính theo cent (x100)
            'vnp_CreateDate'     => $now->format('YmdHis'),
            'vnp_CurrCode'       => 'VND',
            'vnp_IpAddr'         => $cleanIp,
            'vnp_Locale'         => 'vn',
            'vnp_OrderInfo'      => 'Thanh toan don hang ' . $order->order_code,
            'vnp_OrderType'      => 'other',
            'vnp_ReturnUrl'      => $vnpReturnUrl,
            'vnp_TxnRef'         => $txnRef,
            'vnp_ExpireDate'     => $now->copy()->addMinutes(15)->format('YmdHis'),
        ];

        // Sắp xếp tham số theo alphabet
        ksort($inputData);

        // Tạo chuỗi băm và chuỗi query theo chuẩn VNPay (urlencode key và value)
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

        $secureHash = hash_hmac('sha512', $hashData, (string) $vnpHashSecret);
        $paymentUrl = rtrim($vnpUrl, '?') . '?' . $query . 'vnp_SecureHash=' . $secureHash;

        // Lưu bản ghi giao dịch ở trạng thái pending
        Payment::create([
            'order_id'    => $order->id,
            'method'      => Order::PAYMENT_VNPAY,
            'amount'      => $order->total_amount,
            'vnp_txn_ref' => $txnRef,
            'status'      => Payment::STATUS_PENDING,
        ]);

        return $paymentUrl;
    }

    // Xác thực chữ ký dữ liệu từ VNPay gửi về (Return URL & IPN)
    public function verifySignature(array $payload): bool
    {
        $vnpHashSecret = (string) config('vnpay.hash_secret');

        if (empty($vnpHashSecret)) {
            return false;
        }

        $receivedHash = (string) ($payload['vnp_SecureHash'] ?? '');

        if (empty($receivedHash)) {
            return false;
        }

        // Chỉ lấy các tham số bắt đầu bằng vnp_ và loại bỏ các trường chữ ký
        $inputData = [];
        foreach ($payload as $key => $value) {
            if (str_starts_with($key, 'vnp_') && $key !== 'vnp_SecureHash' && $key !== 'vnp_SecureHashType') {
                $inputData[$key] = $value;
            }
        }

        // Sắp xếp theo thứ tự alphabet của key
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

    // Xử lý return URL khi khách được chuyển hướng về trình duyệt
    public function handleReturn(array $payload): array
    {
        // 1. Kiểm tra chữ ký
        if (! $this->verifySignature($payload)) {
            Log::warning('VNPay Return: Chữ ký không hợp lệ', ['payload' => $this->sanitizeLog($payload)]);
            return [
                'success'     => false,
                'order'       => null,
                'status'      => 'failed',
                'code'        => (string) ($payload['vnp_ResponseCode'] ?? '97'),
                'transaction' => null,
                'error'       => 'invalid_signature',
            ];
        }

        $txnRef = (string) ($payload['vnp_TxnRef'] ?? '');
        $payment = Payment::where('vnp_txn_ref', $txnRef)->first();

        if ($payment === null) {
            Log::warning('VNPay Return: Không tìm thấy giao dịch', ['txnRef' => $txnRef]);
            return [
                'success'     => false,
                'order'       => null,
                'status'      => 'failed',
                'code'        => (string) ($payload['vnp_ResponseCode'] ?? '01'),
                'transaction' => null,
                'error'       => 'order_not_found',
            ];
        }

        $order = $payment->order;

        if ($order === null) {
            return [
                'success'     => false,
                'order'       => null,
                'status'      => 'failed',
                'code'        => '01',
                'transaction' => null,
                'error'       => 'order_not_found',
            ];
        }

        // 2. Kiểm tra số tiền
        $expectedAmount = (int) ($order->total_amount * 100);
        $receivedAmount = (int) ($payload['vnp_Amount'] ?? 0);

        if ($expectedAmount !== $receivedAmount) {
            Log::error('VNPay Return: Số tiền không khớp', [
                'order_id' => $order->id,
                'expected' => $expectedAmount,
                'received' => $receivedAmount,
            ]);
            return [
                'success'     => false,
                'order'       => $order,
                'status'      => 'failed',
                'code'        => '04',
                'transaction' => null,
                'error'       => 'invalid_amount',
            ];
        }

        // 3. Phân loại kết quả giao dịch
        $responseCode      = (string) ($payload['vnp_ResponseCode'] ?? '99');
        $transactionNo    = $payload['vnp_TransactionNo'] ?? null;
        $bankCode         = $payload['vnp_BankCode'] ?? null;
        $isSuccess        = ($responseCode === '00');
        $isCancelled      = ($responseCode === '24');

        // Cập nhật bản ghi Payment và Order an toàn trong Transaction
        DB::transaction(function () use ($payment, $order, $payload, $isSuccess, $responseCode, $transactionNo, $bankCode) {
            $payment->status            = $isSuccess ? Payment::STATUS_SUCCESS : Payment::STATUS_FAILED;
            $payment->vnp_response_code = $responseCode;
            $payment->vnp_transaction_no = $transactionNo;
            $payment->bank_code         = $bankCode;
            $payment->raw_response      = $payload;
            $payment->paid_at           = $isSuccess ? now() : null;
            $payment->save();

            // Chỉ cập nhật trạng thái đơn sang 'paid' khi thanh toán thành công
            if ($isSuccess && $order->payment_status !== 'paid') {
                $order->payment_status = 'paid';
                $order->save();

                // Tự động phân bổ lô theo FEFO nếu đơn đang chờ duyệt
                if ($order->status === Order::STATUS_PENDING) {
                    $sales = \App\Models\User::where('role', 'sales')->first()
                        ?? \App\Models\User::where('role', 'admin')->first()
                        ?? \App\Models\User::first();
                    if ($sales) {
                        try {
                            app(OrderService::class)->confirmOrder($order, $sales);
                        } catch (\Throwable $e) {
                            Log::warning('Không thể auto-confirm sau VNPay return: ' . $e->getMessage());
                        }
                    }
                }
            }
        });

        $statusStr = $isSuccess ? 'success' : ($isCancelled ? 'cancelled' : 'failed');

        return [
            'success'     => $isSuccess,
            'order'       => $order->fresh(),
            'status'      => $statusStr,
            'code'        => $responseCode,
            'transaction' => $transactionNo,
            'error'       => $isSuccess ? null : ($isCancelled ? 'customer_cancelled' : 'transaction_failed'),
        ];
    }

    // Xử lý IPN webhook server-to-server từ VNPay
    // Chuẩn Idempotent, không bao giờ cập nhật sai đơn hàng đã thanh toán
    public function handleIpn(array $payload): array
    {
        // Bước 1: Kiểm tra chữ ký
        if (! $this->verifySignature($payload)) {
            Log::warning('VNPay IPN: Chữ ký không hợp lệ', ['payload' => $this->sanitizeLog($payload)]);
            return [
                'RspCode' => '97',
                'Message' => 'Invalid signature',
            ];
        }

        // Bước 2: Tìm giao dịch và đơn hàng
        $txnRef  = (string) ($payload['vnp_TxnRef'] ?? '');
        $payment = Payment::where('vnp_txn_ref', $txnRef)->first();

        if ($payment === null) {
            Log::warning('VNPay IPN: Không tìm thấy giao dịch', ['txnRef' => $txnRef]);
            return [
                'RspCode' => '01',
                'Message' => 'Order not found',
            ];
        }

        $order = $payment->order;

        if ($order === null) {
            return [
                'RspCode' => '01',
                'Message' => 'Order not found',
            ];
        }

        // Bước 3: Kiểm tra số tiền
        $expectedAmount = (int) ($order->total_amount * 100);
        $receivedAmount = (int) ($payload['vnp_Amount'] ?? 0);

        if ($expectedAmount !== $receivedAmount) {
            Log::error('VNPay IPN: Sai số tiền', [
                'order_id' => $order->id,
                'expected' => $expectedAmount,
                'received' => $receivedAmount,
            ]);
            return [
                'RspCode' => '04',
                'Message' => 'Invalid amount',
            ];
        }

        // Bước 4: Kiểm tra trạng thái đơn (Idempotency: nếu đã cập nhật rồi thì báo 02)
        if ($order->payment_status === 'paid') {
            return [
                'RspCode' => '02',
                'Message' => 'Order already confirmed',
            ];
        }

        // Bước 5: Kiểm tra kết quả giao dịch
        $responseCode      = (string) ($payload['vnp_ResponseCode'] ?? '99');
        $transactionStatus = (string) ($payload['vnp_TransactionStatus'] ?? $responseCode);
        $isSuccess         = ($responseCode === '00' && $transactionStatus === '00');

        DB::transaction(function () use ($payment, $order, $payload, $isSuccess, $responseCode) {
            $payment->status             = $isSuccess ? Payment::STATUS_SUCCESS : Payment::STATUS_FAILED;
            $payment->vnp_response_code  = $responseCode;
            $payment->vnp_transaction_no = $payload['vnp_TransactionNo'] ?? null;
            $payment->bank_code          = $payload['vnp_BankCode'] ?? null;
            $payment->raw_response       = $payload;
            $payment->paid_at            = $isSuccess ? now() : null;
            $payment->save();

            if ($isSuccess) {
                $order->payment_status = 'paid';
                $order->save();

                // Tự động phân bổ lô theo FEFO nếu đơn đang chờ duyệt
                if ($order->status === Order::STATUS_PENDING) {
                    $sales = \App\Models\User::where('role', 'sales')->first()
                        ?? \App\Models\User::where('role', 'admin')->first()
                        ?? \App\Models\User::first();
                    if ($sales) {
                        try {
                            app(OrderService::class)->confirmOrder($order, $sales);
                        } catch (\Throwable $e) {
                            Log::warning('Không thể auto-confirm sau VNPay IPN: ' . $e->getMessage());
                        }
                    }
                }
            }
        });

        Log::info('VNPay IPN: Xử lý thành công', [
            'order_id' => $order->id,
            'status'   => $isSuccess ? 'paid' : 'failed',
            'code'     => $responseCode,
        ]);

        return [
            'RspCode' => '00',
            'Message' => 'Confirm Success',
        ];
    }

    // Ẩn các thông tin nhạy cảm trước khi ghi log
    private function sanitizeLog(array $data): array
    {
        $sanitized = $data;
        unset($sanitized['vnp_HashSecret']);
        return $sanitized;
    }
}

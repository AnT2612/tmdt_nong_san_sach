<?php

namespace App\Services;

use App\Models\Order;

class VietQRService
{
    // Tạo thông tin chuyển khoản VietQR cho một đơn hàng
    public function getVietQrPayload(Order $order): array
    {
        $bank        = (string) config('vietqr.bank', 'MB');
        $accountNo   = (string) config('vietqr.account_no', '0987654321');
        $accountName = (string) config('vietqr.account_name', 'TRAN QUOC KHANH');
        $template    = (string) config('vietqr.template', 'compact2');

        $amount = (int) round((float) $order->total_amount);

        // Nội dung chuyển khoản: NSS + Mã đơn (chỉ giữ ký tự chữ và số để ứng dụng ngân hàng không bị lỗi ký tự đặc biệt)
        $cleanCode = preg_replace('/[^A-Za-z0-9]/', '', (string) $order->order_code);
        $memo      = 'NSS ' . $cleanCode;

        // Tạo URL ảnh VietQR chuẩn Napas 24/7 từ VietQR API
        $query = http_build_query([
            'amount'      => $amount,
            'addInfo'     => $memo,
            'accountName' => $accountName,
        ]);

        $qrUrl = "https://img.vietqr.io/image/{$bank}-{$accountNo}-{$template}.png?{$query}";

        return [
            'bank'         => $bank,
            'account_no'   => $accountNo,
            'account_name' => $accountName,
            'amount'       => $amount,
            'memo'         => $memo,
            'qr_url'       => $qrUrl,
        ];
    }
}

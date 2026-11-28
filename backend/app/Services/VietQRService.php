<?php

namespace App\Services;

use App\Models\Order;

class VietQRService
{
    public function getVietQrPayload(Order $order): array
    {
        $bank        = (string) config('vietqr.bank', 'MB');
        $accountNo   = (string) config('vietqr.account_no', '0987654321');
        $accountName = (string) config('vietqr.account_name', 'TRAN QUOC KHANH');
        $template    = (string) config('vietqr.template', 'compact2');

        $amount    = (int) round((float) $order->tong_tien);
        $cleanCode = preg_replace('/[^A-Za-z0-9]/', '', (string) $order->ma_don);
        $memo      = 'NSS ' . $cleanCode;

        $query = http_build_query([
            'amount'      => $amount,
            'addInfo'     => $memo,
            'accountName' => $accountName,
        ]);

        return [
            'bank'         => $bank,
            'account_no'   => $accountNo,
            'account_name' => $accountName,
            'amount'       => $amount,
            'memo'         => $memo,
            'qr_url'       => "https://img.vietqr.io/image/{$bank}-{$accountNo}-{$template}.png?{$query}",
        ];
    }
}

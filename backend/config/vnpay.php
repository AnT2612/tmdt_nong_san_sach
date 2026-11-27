<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Cấu hình cổng thanh toán VNPay Sandbox.
 */

return [
    'tmn_code'    => env('VNPAY_TMN_CODE'),
    'hash_secret' => env('VNPAY_HASH_SECRET'),
    'url'         => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
    'return_url'  => env('VNPAY_RETURN_URL'),
    'ipn_url'     => env('VNPAY_IPN_URL'),
];

<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (0306241206) & Đào Quốc Đạt (0306241190)
 * CHỨC NĂNG: Cấu hình mã VietQR (Napas 24/7) để nhận tiền thật trực tiếp vào tài khoản ngân hàng cá nhân.
 */

return [
    'bank'         => env('VIETQR_BANK', 'MB'),
    'account_no'   => env('VIETQR_ACCOUNT_NO', '0987654321'),
    'account_name' => env('VIETQR_ACCOUNT_NAME', 'TRAN QUOC KHANH'),
    'template'     => env('VIETQR_TEMPLATE', 'compact2'),
];

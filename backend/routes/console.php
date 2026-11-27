<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171) & Trần Quốc Khánh (0306241206)
 * CHỨC NĂNG: Khai báo lệnh Console và Lịch chạy tự động (Schedule).
 *
 *   - Lệnh 'subscription:generate-orders': Tự động sinh đơn cho các gói rau củ định kỳ.
 *   - Lịch chạy: 18:00 mỗi ngày (đúng theo đặc tả Use Case UC-02 trong tài liệu Tuần 2).
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lịch chạy tự động quét gói định kỳ hàng ngày lúc 18:00 (UC-02)
Schedule::command('subscription:generate-orders')
    ->dailyAt('18:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/subscription-cron.log'));

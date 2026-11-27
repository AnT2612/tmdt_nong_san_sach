<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverySchedule extends Model
{
    public const STATUS_CHO_SINH_DON = 'cho_sinh_don';
    public const STATUS_DA_SINH_DON  = 'da_sinh_don';
    public const STATUS_THIEU_HANG   = 'thieu_hang';
    public const STATUS_DA_GIAO      = 'da_giao';
    public const STATUS_BO_QUA       = 'bo_qua';

    protected $table = 'delivery_schedules';

    protected $fillable = [
        'subscription_id',
        'ngay_giao',
        'khung_gio',
        'order_id',
        'trang_thai',
    ];

    protected function casts(): array
    {
        return [
            'ngay_giao' => 'date',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

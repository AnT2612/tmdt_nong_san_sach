<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public $timestamps = false;

    public const CONG_COD    = 'cod';
    public const CONG_MOMO   = 'momo';
    public const CONG_VNPAY  = 'vnpay';
    public const CONG_VIETQR = 'vietqr';

    public const STATUS_CHO        = 'cho';
    public const STATUS_THANH_CONG = 'thanh_cong';
    public const STATUS_THAT_BAI   = 'that_bai';
    public const STATUS_HOAN_TIEN  = 'hoan_tien';

    protected $table = 'payments';

    protected $fillable = [
        'order_id',
        'subscription_id',
        'cong_thanh_toan',
        'ma_giao_dich',
        'so_tien',
        'trang_thai',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'so_tien'    => 'decimal:0',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }
}

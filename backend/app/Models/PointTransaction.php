<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    public $timestamps = false;

    public const LOAI_CONG = 'cong_diem';
    public const LOAI_TRU  = 'tru_diem';
    public const LOAI_HOAN = 'hoan_diem';

    protected $table = 'point_transactions';

    protected $fillable = [
        'user_id',
        'order_id',
        'loai_giao_dich',
        'so_diem',
        'mo_ta',
        'ngay_tao',
    ];

    protected function casts(): array
    {
        return [
            'so_diem'  => 'integer',
            'ngay_tao' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

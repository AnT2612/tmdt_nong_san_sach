<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Complaint extends Model
{
    public const HUONG_HOAN_TIEN = 'hoan_tien';
    public const HUONG_GIAO_BU   = 'giao_bu';
    public const HUONG_TU_CHOI   = 'tu_choi';

    public const STATUS_MOI        = 'moi';
    public const STATUS_DANG_XU_LY = 'dang_xu_ly';
    public const STATUS_DA_XU_LY   = 'da_xu_ly';

    protected $table = 'complaints';

    protected $fillable = [
        'order_detail_id',
        'user_id',
        'ly_do',
        'noi_dung',
        'minh_chung',
        'huong_xu_ly',
        'so_tien_hoan',
        'trang_thai',
        'xu_ly_boi',
    ];

    protected function casts(): array
    {
        return [
            'so_tien_hoan' => 'decimal:0',
        ];
    }

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_detail_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'xu_ly_boi');
    }

    public function replacementOrder(): HasOne
    {
        return $this->hasOne(Order::class, 'complaint_id');
    }
}

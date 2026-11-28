<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchAlert extends Model
{
    public const STATUS_DANG_MO           = 'dang_mo';
    public const STATUS_DA_XU_LY          = 'da_xu_ly';
    public const STATUS_CHUYEN_TIEP_ADMIN = 'chuyen_tiep_admin';

    protected $table = 'batch_alerts';

    protected $fillable = [
        'batch_id',
        'handled_by',
        'ngay_canh_bao',
        'trang_thai',
        'hanh_dong_xu_ly',
        'batch_dang_mo',
    ];

    protected function casts(): array
    {
        return [
            'ngay_canh_bao' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}

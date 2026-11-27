<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmingLog extends Model
{
    protected $table = 'farming_logs';

    protected $fillable = [
        'batch_id',
        'farm_staff_id',
        'hanh_dong',
        'mo_ta',
        'hinh_anh_minh_chung',
        'thoi_gian',
    ];

    protected function casts(): array
    {
        return [
            'thoi_gian' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function farmStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farm_staff_id');
    }
}

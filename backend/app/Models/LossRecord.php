<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LossRecord extends Model
{
    public $timestamps = false;

    protected $table = 'loss_records';

    protected $fillable = [
        'batch_id',
        'so_luong',
        'ly_do',
        'hinh_anh',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'so_luong'   => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

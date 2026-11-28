<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    public $timestamps = false;

    protected $table = 'certificates';

    protected $fillable = [
        'batch_id',
        'uploaded_by',
        'ten_chung_nhan',
        'file_minh_chung',
        'ngay_cap',
        'ngay_het_han',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'ngay_cap'     => 'date',
            'ngay_het_han' => 'date',
            'created_at'   => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

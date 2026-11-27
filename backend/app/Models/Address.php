<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $table = 'addresses';

    protected $fillable = [
        'user_id',
        'dia_chi',
        'quan_huyen',
        'tinh_thanh',
        'vi_do',
        'kinh_do',
        'mac_dinh',
    ];

    protected function casts(): array
    {
        return [
            'vi_do'    => 'decimal:6',
            'kinh_do'  => 'decimal:6',
            'mac_dinh' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

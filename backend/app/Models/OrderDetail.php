<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderDetail extends Model
{
    protected $table = 'order_details';

    protected $fillable = [
        'order_id',
        'product_id',
        'batch_id',
        'so_luong',
        'don_gia',
        'thanh_tien',
    ];

    protected function casts(): array
    {
        return [
            'so_luong'   => 'decimal:2',
            'don_gia'    => 'decimal:0',
            'thanh_tien' => 'decimal:0',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'order_detail_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'sku',
        'ten_san_pham',
        'slug',
        'mo_ta',
        'hinh_anh_dai_dien',
        'gia_ban_co_ban',
        'don_vi_tinh',
        'chung_nhan',
        'trang_thai',
    ];

    protected function casts(): array
    {
        return [
            'gia_ban_co_ban' => 'decimal:0',
            'trang_thai'     => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'product_id');
    }

    public function availableBatches(): HasMany
    {
        return $this->hasMany(Batch::class, 'product_id')
            ->available()
            ->fefoOrder();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    public function totalAvailableQuantity(): float
    {
        return round((float) $this->availableBatches()->sum('so_luong_ton'), 2);
    }

    public function currentPrice(): float
    {
        $nearest = $this->availableBatches()->first();

        if ($nearest !== null && $nearest->gia_xa_hang !== null) {
            return (float) $nearest->gia_xa_hang;
        }

        return (float) $this->gia_ban_co_ban;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    public const STATUS_CON_HANG = 'con_hang';
    public const STATUS_CAN_HAN  = 'can_han';
    public const STATUS_HET_HANG = 'het_hang';
    public const STATUS_HET_HAN  = 'het_han';

    protected $table = 'batches';

    protected $fillable = [
        'product_id',
        'ma_lo',
        'ngay_thu_hoach',
        'ngay_het_han',
        'so_luong_nhap',
        'so_luong_ton',
        'so_luong_hao_hut',
        'qr_code',
        'trang_thai',
        'gia_xa_hang',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ngay_thu_hoach'   => 'date',
            'ngay_het_han'     => 'date',
            'so_luong_nhap'    => 'decimal:2',
            'so_luong_ton'     => 'decimal:2',
            'so_luong_hao_hut' => 'decimal:2',
            'gia_xa_hang'      => 'decimal:0',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function farmingLogs(): HasMany
    {
        return $this->hasMany(FarmingLog::class, 'batch_id')->orderBy('thoi_gian');
    }

    public function lossRecords(): HasMany
    {
        return $this->hasMany(LossRecord::class, 'batch_id')->latest('created_at');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(BatchAlert::class, 'batch_id')->latest('ngay_canh_bao');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'batch_id')->latest('ngay_cap');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'batch_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->whereIn('trang_thai', [self::STATUS_CON_HANG, self::STATUS_CAN_HAN])
            ->where('so_luong_ton', '>', 0)
            ->whereDate('ngay_het_han', '>=', now()->toDateString());
    }

    public function scopeFefoOrder(Builder $query): Builder
    {
        return $query
            ->orderBy('ngay_het_han', 'asc')
            ->orderBy('ngay_thu_hoach', 'asc')
            ->orderBy('id', 'asc');
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->ngay_het_han === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->ngay_het_han->copy()->startOfDay(), false);
    }

    public function effectivePrice(): float
    {
        if ($this->gia_xa_hang !== null) {
            return (float) $this->gia_xa_hang;
        }

        return (float) ($this->product?->gia_ban_co_ban ?? 0);
    }
}

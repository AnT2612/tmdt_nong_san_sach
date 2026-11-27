<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'ten_goi',
        'ngay_giao_trong_tuan',
        'ngay_bat_dau',
        'ngay_ket_thuc',
        'dia_chi_giao',
        'trang_thai',
    ];

    protected function casts(): array
    {
        return [
            'ngay_bat_dau'  => 'date',
            'ngay_ket_thuc' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class, 'subscription_id');
    }

    public function deliverySchedules(): HasMany
    {
        return $this->hasMany(DeliverySchedule::class, 'subscription_id')->orderBy('ngay_giao');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'subscription_id')->latest('ngay_tao');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'subscription_id');
    }

    public function weekdayArray(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->ngay_giao_trong_tuan))));
    }
}

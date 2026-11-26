<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_url',
        'price',
        'duration_days',
        'delivery_weekdays',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'delivery_weekdays' => 'array',
            'price'             => 'decimal:0',
            'duration_days'     => 'integer',
            'is_active'         => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionPlanItem::class);
    }

    public function customerSubscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }
}

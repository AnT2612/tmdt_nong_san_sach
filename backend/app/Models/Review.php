<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{

    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'comment',
        'media',
        'is_visible',

    public $timestamps = false;

    protected $table = 'reviews';

    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'so_sao',
        'noi_dung',
        'created_at',

    ];

    protected function casts(): array
    {
        return [

            'media'      => 'array',
            'rating'     => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);

            'so_sao'     => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');

    }

    public function order(): BelongsTo
    {

        return $this->belongsTo(Order::class);

        return $this->belongsTo(Order::class, 'order_id');

    }
}

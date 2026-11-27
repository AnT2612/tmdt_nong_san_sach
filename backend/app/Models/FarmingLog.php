<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'created_by',
        'activity_type',
        'activity_date',
        'title',
        'description',
        'media',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'media'         => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

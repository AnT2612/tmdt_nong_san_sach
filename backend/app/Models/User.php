<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'role_id',
        'ho_ten',
        'email',
        'so_dien_thoai',
        'mat_khau',
        'diem_tich_luy',
        'trang_thai',
    ];

    protected $hidden = [
        'mat_khau',
        'remember_token',
    ];

    protected $appends = [
        'ten_vai_tro',
    ];

    protected function casts(): array
    {
        return [
            'mat_khau'      => 'hashed',
            'diem_tich_luy' => 'integer',
            'trang_thai'    => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->mat_khau;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->ten_vai_tro,
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function getTenVaiTroAttribute(): ?string
    {
        return $this->role?->ten_vai_tro;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->ten_vai_tro, $roles, true);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'user_id');
    }
}

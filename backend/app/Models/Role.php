<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMIN = 'admin';
    public const SALES = 'sales';
    public const FARM_STAFF = 'farm_staff';
    public const CUSTOMER = 'customer';

    protected $table = 'roles';

    protected $fillable = [
        'ten_vai_tro',
        'mo_ta',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}

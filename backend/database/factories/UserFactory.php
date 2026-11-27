<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $role = Role::firstOrCreate(
            ['ten_vai_tro' => Role::CUSTOMER],
            ['mo_ta' => 'Khách hàng']
        );

        return [
            'role_id'        => $role->id,
            'ho_ten'         => fake()->name(),
            'email'          => fake()->unique()->safeEmail(),
            'so_dien_thoai'  => fake()->unique()->numerify('09########'),
            'mat_khau'       => static::$password ??= Hash::make('12345678'),
            'diem_tich_luy'  => 0,
            'trang_thai'     => true,
            'remember_token' => Str::random(10),
        ];
    }
}

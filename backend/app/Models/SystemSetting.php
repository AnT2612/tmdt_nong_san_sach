<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    // Đọc một cấu hình, trả về giá trị mặc định nếu chưa có
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    // Ghi đè hoặc tạo mới một cấu hình
    public static function put(string $key, string $value, ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description],
        );
    }
}

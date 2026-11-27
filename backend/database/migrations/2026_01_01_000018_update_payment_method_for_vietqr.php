<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method VARCHAR(30) NOT NULL DEFAULT 'cod'");
        DB::statement("ALTER TABLE payments MODIFY COLUMN method VARCHAR(30) NOT NULL DEFAULT 'cod'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod', 'vnpay', 'vietqr') NOT NULL DEFAULT 'cod'");
        DB::statement("ALTER TABLE payments MODIFY COLUMN method ENUM('cod', 'vnpay', 'vietqr') NOT NULL DEFAULT 'cod'");
    }
};

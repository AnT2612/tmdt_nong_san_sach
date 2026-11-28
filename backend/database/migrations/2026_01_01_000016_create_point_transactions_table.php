<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('loai_giao_dich', ['cong_diem', 'tru_diem', 'hoan_diem']);
            $table->unsignedInteger('so_diem');
            $table->string('mo_ta', 255)->nullable();
            $table->dateTime('ngay_tao');

            $table->index(['user_id', 'ngay_tao'], 'idx_pt_user_ngay_tao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};

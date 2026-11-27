<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ten_goi', 100);
            $table->string('ngay_giao_trong_tuan', 20);
            $table->date('ngay_bat_dau');
            $table->date('ngay_ket_thuc');
            $table->string('dia_chi_giao', 255);
            $table->enum('trang_thai', ['active', 'paused', 'cancelled', 'expired'])->default('active');
            $table->timestamps();

            $table->index('user_id', 'idx_sub_user_id');
            $table->index('trang_thai', 'idx_sub_trang_thai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

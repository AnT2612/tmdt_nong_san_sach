<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->date('ngay_giao');
            $table->string('khung_gio', 30)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->enum('trang_thai', ['cho_sinh_don', 'da_sinh_don', 'thieu_hang', 'da_giao', 'bo_qua'])->default('cho_sinh_don');
            $table->timestamps();

            $table->unique(['subscription_id', 'ngay_giao'], 'uq_ds_subscription_ngay');
            $table->index(['ngay_giao', 'trang_thai'], 'idx_ds_ngay_giao_trang_thai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_schedules');
    }
};

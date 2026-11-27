<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->enum('cong_thanh_toan', ['cod', 'momo', 'vnpay', 'vietqr']);
            $table->string('ma_giao_dich', 64)->nullable()->unique('uq_pay_ma_giao_dich');
            $table->decimal('so_tien', 12, 0);
            $table->enum('trang_thai', ['cho', 'thanh_cong', 'that_bai', 'hoan_tien'])->default('cho');
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_id', 'idx_pay_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_detail_id')->constrained('order_details')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ly_do', 255);
            $table->text('noi_dung')->nullable();
            $table->string('minh_chung', 255);
            $table->enum('huong_xu_ly', ['hoan_tien', 'giao_bu', 'tu_choi'])->nullable();
            $table->decimal('so_tien_hoan', 12, 0)->nullable();
            $table->enum('trang_thai', ['moi', 'dang_xu_ly', 'da_xu_ly'])->default('moi');
            $table->foreignId('xu_ly_boi')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('order_detail_id', 'idx_cmp_order_detail_id');
            $table->index('trang_thai', 'idx_cmp_trang_thai');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('complaint_id', 'fk_orders_complaint_id')
                ->references('id')
                ->on('complaints')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('fk_orders_complaint_id');
        });
        Schema::dropIfExists('complaints');
    }
};

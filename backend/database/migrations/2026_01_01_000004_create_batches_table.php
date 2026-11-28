<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('ma_lo', 30)->unique('uq_batches_ma_lo');
            $table->date('ngay_thu_hoach');
            $table->date('ngay_het_han')->index('idx_batches_ngay_het_han');
            $table->decimal('so_luong_nhap', 10, 2);
            $table->decimal('so_luong_ton', 10, 2);
            $table->decimal('so_luong_hao_hut', 10, 2)->default(0);
            $table->string('qr_code', 255)->unique('uq_batches_qr_code');
            $table->enum('trang_thai', ['con_hang', 'can_han', 'het_hang', 'het_han'])->default('con_hang');
            $table->decimal('gia_xa_hang', 12, 0)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'ngay_het_han'], 'idx_batches_fefo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->restrictOnDelete();
            $table->decimal('so_luong', 10, 2);
            $table->decimal('don_gia', 12, 0);
            $table->decimal('thanh_tien', 12, 0);
            $table->timestamps();

            $table->index('order_id', 'idx_od_order_id');
            $table->index('batch_id', 'idx_od_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};

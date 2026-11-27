<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->string('product_name', 150);   // Ảnh chụp tên lúc mua
            $table->string('product_unit', 20);
            $table->decimal('unit_price', 12, 0);  // Ảnh chụp giá lúc mua
            $table->decimal('quantity', 10, 3);
            $table->decimal('line_total', 12, 0);

            $table->decimal('quantity_prepared', 10, 3)->default(0);

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

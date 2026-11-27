<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnUpdate()->restrictOnDelete();

            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->string('sku', 50)->unique();

            // Nông sản bán theo kg / bó / hộp nên số lượng là số thập phân
            $table->string('unit', 20)->default('kg');

            $table->decimal('price', 12, 0);            // Giá bán thường (VNĐ)
            $table->decimal('sale_price', 12, 0)->nullable(); // Giá khuyến mãi nếu có

            $table->text('description')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->json('gallery')->nullable();         // Danh sách ảnh phụ

            $table->string('origin', 255)->nullable();   // Vùng trồng
            $table->string('certification_summary', 255)->nullable();
            $table->unsignedSmallInteger('shelf_life_days')->default(7);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

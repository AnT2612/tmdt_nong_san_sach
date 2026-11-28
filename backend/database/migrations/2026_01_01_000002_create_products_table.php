<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('sku', 30)->unique('uq_products_sku');
            $table->string('ten_san_pham', 150);
            $table->string('slug', 170)->unique('uq_products_slug');
            $table->text('mo_ta')->nullable();
            $table->string('hinh_anh_dai_dien', 255)->nullable();
            $table->decimal('gia_ban_co_ban', 12, 0);
            $table->string('don_vi_tinh', 20);
            $table->string('chung_nhan', 100)->nullable();
            $table->boolean('trang_thai')->default(true);
            $table->timestamps();

            $table->index('category_id', 'idx_products_category_id');
            if (DB::getDriverName() === 'mysql') {
                $table->fullText('ten_san_pham', 'ft_products_ten');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

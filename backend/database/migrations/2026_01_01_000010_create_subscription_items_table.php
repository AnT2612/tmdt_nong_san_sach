<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('so_luong', 10, 2);
            $table->timestamps();

            $table->unique(['subscription_id', 'product_id'], 'uq_sub_items_sub_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};

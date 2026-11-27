<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_batch_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();

            $table->decimal('quantity', 10, 3);

            $table->timestamps();

            $table->index(['order_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_batch_allocations');
    }
};

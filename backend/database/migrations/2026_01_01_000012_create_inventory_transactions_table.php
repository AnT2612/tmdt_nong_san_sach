<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();

            $table->enum('type', ['import', 'export_order', 'dispose', 'adjust']);

            // Số lượng luôn dương, chiều tăng/giảm do cột `type` quyết định
            $table->decimal('quantity', 12, 3);

            // Số lượng còn lại của lô sau khi thực hiện phiếu này
            $table->decimal('balance_after', 12, 3);

            // Lý do hủy hàng: 'damaged', 'expired', 'overripe', 'other'
            $table->string('reason', 50)->nullable();
            $table->string('note', 500)->nullable();

            // Nếu là xuất cho đơn hàng thì lưu id đơn để tra ngược
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['batch_id', 'type']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};

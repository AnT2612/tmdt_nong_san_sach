<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loss_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->decimal('so_luong', 10, 2);
            $table->string('ly_do', 255);
            $table->string('hinh_anh', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('batch_id', 'idx_lr_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loss_records');
    }
};

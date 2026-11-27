<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farming_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('farm_staff_id')->constrained('users')->restrictOnDelete();
            $table->string('hanh_dong', 100);
            $table->text('mo_ta')->nullable();
            $table->string('hinh_anh_minh_chung', 255)->nullable();
            $table->dateTime('thoi_gian');
            $table->timestamps();

            $table->index(['batch_id', 'thoi_gian'], 'idx_fl_batch_thoi_gian');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farming_logs');
    }
};

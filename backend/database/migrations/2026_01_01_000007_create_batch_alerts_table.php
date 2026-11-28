<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ngay_canh_bao');
            $table->enum('trang_thai', ['dang_mo', 'da_xu_ly', 'chuyen_tiep_admin'])->default('dang_mo');
            $table->string('hanh_dong_xu_ly', 255)->nullable();
            $table->unsignedBigInteger('batch_dang_mo')->nullable()->unique('uq_ba_open_batch');
            $table->timestamps();

            $table->index('batch_id', 'idx_ba_batch_id');
            $table->index(['trang_thai', 'ngay_canh_bao'], 'idx_ba_trang_thai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_alerts');
    }
};

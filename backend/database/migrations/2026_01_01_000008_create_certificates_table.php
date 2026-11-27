<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('ten_chung_nhan', 150);
            $table->string('file_minh_chung', 255);
            $table->date('ngay_cap');
            $table->date('ngay_het_han')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('batch_id', 'idx_cert_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};

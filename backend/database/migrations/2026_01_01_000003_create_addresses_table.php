<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('dia_chi', 255);
            $table->string('quan_huyen', 100);
            $table->string('tinh_thanh', 100);
            $table->decimal('vi_do', 9, 6)->nullable();
            $table->decimal('kinh_do', 9, 6)->nullable();
            $table->boolean('mac_dinh')->default(false);
            $table->timestamps();

            $table->index('user_id', 'idx_addr_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};

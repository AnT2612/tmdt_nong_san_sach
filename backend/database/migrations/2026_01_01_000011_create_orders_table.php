<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('ma_don', 20)->unique('uq_orders_ma_don');
            $table->enum('loai_don', ['don_le', 'dinh_ky', 'giao_bu'])->default('don_le');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->unsignedBigInteger('complaint_id')->nullable()->unique('uq_orders_complaint_id');
            $table->string('ten_nguoi_nhan', 100);
            $table->string('so_dien_thoai', 15);
            $table->string('email_nguoi_nhan', 150)->nullable();
            $table->string('dia_chi_giao', 255);
            $table->enum('loai_giao', ['thuong', 'hoa_toc'])->default('thuong');
            $table->decimal('phi_van_chuyen', 12, 0)->default(0);
            $table->decimal('khoang_cach_km', 6, 2)->nullable();
            $table->decimal('tong_tien', 12, 0);
            $table->unsignedInteger('diem_su_dung')->default(0);
            $table->decimal('tien_giam_diem', 12, 0)->default(0);
            $table->enum('trang_thai', ['cho_duyet', 'da_duyet', 'dang_giao', 'hoan_thanh', 'da_huy'])->default('cho_duyet');
            $table->dateTime('ngay_tao');
            $table->dateTime('ngay_cap_nhat')->nullable();
            $table->date('ngay_giao_du_kien')->nullable();

            $table->index('user_id', 'idx_orders_user_id');
            $table->index(['trang_thai', 'ngay_tao'], 'idx_orders_trang_thai_ngay_tao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

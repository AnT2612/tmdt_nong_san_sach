<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const CREATED_AT = 'ngay_tao';
    public const UPDATED_AT = 'ngay_cap_nhat';

    public const LOAI_DON_LE    = 'don_le';
    public const LOAI_DINH_KY   = 'dinh_ky';
    public const LOAI_GIAO_BU   = 'giao_bu';

    public const LOAI_GIAO_THUONG  = 'thuong';
    public const LOAI_GIAO_HOA_TOC = 'hoa_toc';

    public const STATUS_CHO_DUYET  = 'cho_duyet';
    public const STATUS_DA_DUYET   = 'da_duyet';
    public const STATUS_DANG_GIAO  = 'dang_giao';
    public const STATUS_HOAN_THANH = 'hoan_thanh';
    public const STATUS_DA_HUY     = 'da_huy';

    protected $table = 'orders';

    protected $fillable = [
        'ma_don',
        'loai_don',
        'user_id',
        'subscription_id',
        'complaint_id',
        'ten_nguoi_nhan',
        'so_dien_thoai',
        'email_nguoi_nhan',
        'dia_chi_giao',
        'loai_giao',
        'phi_van_chuyen',
        'khoang_cach_km',
        'tong_tien',
        'diem_su_dung',
        'tien_giam_diem',
        'trang_thai',
        'ngay_tao',
        'ngay_cap_nhat',
        'ngay_giao_du_kien',
    ];

    protected function casts(): array
    {
        return [
            'phi_van_chuyen'    => 'decimal:0',
            'khoang_cach_km'    => 'decimal:2',
            'tong_tien'         => 'decimal:0',
            'diem_su_dung'      => 'integer',
            'tien_giam_diem'    => 'decimal:0',
            'ngay_tao'          => 'datetime',
            'ngay_cap_nhat'     => 'datetime',
            'ngay_giao_du_kien' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function sourceComplaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class, 'complaint_id');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id')->latest('created_at');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'order_id');
    }

    public function deliverySchedule(): HasOne
    {
        return $this->hasOne(DeliverySchedule::class, 'order_id');
    }

    public function canBeCancelled(): bool
    {
        return $this->trang_thai === self::STATUS_CHO_DUYET;
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('trang_thai', Payment::STATUS_THANH_CONG)->exists();
    }
}

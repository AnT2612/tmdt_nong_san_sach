<?php

namespace Database\Seeders;

use App\Models\DeliverySchedule;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::where('email', 'khachhang@nongsan.vn')->first();

        if ($customer === null || Subscription::where('user_id', $customer->id)->exists()) {
            return;
        }

        $plan = config('subscriptions.plans.0');
        $start = now()->startOfDay();
        $end = $start->copy()->addDays((int) ($plan['thoi_han_ngay'] ?? 30));

        $subscription = Subscription::create([
            'user_id'              => $customer->id,
            'ten_goi'              => $plan['ten_goi'],
            'ngay_giao_trong_tuan' => $plan['ngay_giao_trong_tuan'],
            'ngay_bat_dau'         => $start->toDateString(),
            'ngay_ket_thuc'        => $end->toDateString(),
            'dia_chi_giao'         => '65 Huỳnh Thúc Kháng, Quận 1, TP. HCM',
            'trang_thai'           => Subscription::STATUS_ACTIVE,
        ]);

        foreach ($plan['items'] as $line) {
            $product = Product::where('sku', $line['sku'])->first();
            if ($product !== null) {
                SubscriptionItem::create([
                    'subscription_id' => $subscription->id,
                    'product_id'      => $product->id,
                    'so_luong'        => $line['so_luong'],
                ]);
            }
        }

        $weekdays = $subscription->weekdayArray();
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $isoWeekday = $cursor->dayOfWeekIso === 7 ? 7 : $cursor->dayOfWeekIso + 1;
            if (in_array($isoWeekday, $weekdays, true)) {
                DeliverySchedule::create([
                    'subscription_id' => $subscription->id,
                    'ngay_giao'       => $cursor->toDateString(),
                    'khung_gio'       => '08:00-10:00',
                    'trang_thai'      => DeliverySchedule::STATUS_CHO_SINH_DON,
                ]);
            }
            $cursor->addDay();
        }

        Payment::create([
            'subscription_id' => $subscription->id,
            'cong_thanh_toan' => Payment::CONG_VNPAY,
            'ma_giao_dich'    => 'SUB-' . $subscription->id . '-' . time(),
            'so_tien'         => $plan['gia_goi'],
            'trang_thai'      => Payment::STATUS_THANH_CONG,
            'created_at'      => now(),
        ]);
    }
}

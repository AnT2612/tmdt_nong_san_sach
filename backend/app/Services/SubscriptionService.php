<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    // Đăng ký gói định kỳ
    public function subscribe(User $customer, SubscriptionPlan $plan, array $data): CustomerSubscription
    {
        $startDate = Carbon::parse($data['start_date'] ?? now())->startOfDay();
        $endDate   = $startDate->copy()->addDays($plan->duration_days);

        $weekdays = $data['delivery_weekdays'] ?? $plan->delivery_weekdays;

        if (empty($weekdays)) {
            throw ValidationException::withMessages([
                'delivery_weekdays' => 'Gói này chưa cấu hình ngày giao hàng.',
            ]);
        }

        $firstDelivery = $this->findNextDeliveryDate($startDate, $weekdays, $endDate);

        return DB::transaction(function () use ($customer, $plan, $data, $startDate, $endDate, $weekdays, $firstDelivery) {
            return CustomerSubscription::create([
                'subscription_code'    => $this->generateSubscriptionCode(),
                'user_id'              => $customer->id,
                'subscription_plan_id' => $plan->id,
                'start_date'           => $startDate,
                'end_date'             => $endDate,
                'delivery_weekdays'    => $weekdays,
                'receiver_name'        => $data['receiver_name'],
                'receiver_phone'       => $data['receiver_phone'],
                'shipping_address'     => $data['shipping_address'],
                'total_paid'           => $plan->price,
                'payment_method'       => $data['payment_method'] ?? 'vnpay',
                'payment_status'       => 'unpaid',
                'status'               => CustomerSubscription::STATUS_ACTIVE,
                'next_delivery_date'   => $firstDelivery,
                'note'                 => $data['note'] ?? null,
            ]);
        });
    }

    // Tự sinh đơn cho các gói tới ngày giao
    public function generateDueOrders(): array
    {
        return Cache::lock('subscription-generate-orders', 60)
            ->block(5, fn () => $this->createDueOrders());
    }

    private function createDueOrders(): array
    {
        $today = now()->startOfDay();

        /** @var \Illuminate\Database\Eloquent\Collection<int, CustomerSubscription> $dueSubscriptions */
        $dueSubscriptions = CustomerSubscription::query()
            ->where('status', CustomerSubscription::STATUS_ACTIVE)
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereDate('next_delivery_date', '<=', $today->toDateString())
            ->with('plan.items.product')
            ->get();

        $createdOrders = [];

        foreach ($dueSubscriptions as $subscription) {
            if (! $this->hasEnoughStock($subscription)) {
                $subscription->note = 'Kỳ giao ' . $today->format('d/m/Y')
                    . ' bị bỏ qua do thiếu hàng. Cần Sales liên hệ khách.';
                $subscription->save();
                continue;
            }

            $order = $this->createOrderForSubscription($subscription, $today);
            $createdOrders[] = $order;

            $subscription->next_delivery_date = $this->findNextDeliveryDate(
                $today->copy()->addDay(),
                $subscription->delivery_weekdays,
                $subscription->end_date,
            );
            $subscription->save();
        }

        return $createdOrders;
    }

    // Bỏ một kỳ giao
    public function skipDelivery(CustomerSubscription $subscription, User $sales): CustomerSubscription
    {
        $currentDate = $subscription->next_delivery_date ?? now();

        $subscription->next_delivery_date = $this->findNextDeliveryDate(
            Carbon::parse($currentDate)->addDay(),
            $subscription->delivery_weekdays,
            $subscription->end_date,
        );

        $subscription->note = "Sales {$sales->full_name} đã dời kỳ giao ngày "
            . Carbon::parse($currentDate)->format('d/m/Y') . ' do thiếu hàng.';

        $subscription->save();

        return $subscription->fresh();
    }

    private function createOrderForSubscription(CustomerSubscription $subscription, Carbon $deliveryDate): Order
    {
        $items = [];

        foreach ($subscription->plan->items as $planItem) {
            $items[] = [
                'product_id' => $planItem->product_id,
                'quantity'   => (float) $planItem->quantity,
            ];
        }

        $orderService = app(OrderService::class);

        $order = $orderService->placeOrder([
            'user_id'          => $subscription->user_id,
            'subscription_id'  => $subscription->id,
            'receiver_name'    => $subscription->receiver_name,
            'receiver_phone'   => $subscription->receiver_phone,
            'shipping_address' => $subscription->shipping_address,
            'note'             => "Đơn tự sinh từ gói {$subscription->subscription_code}.",
            'payment_method'   => $subscription->payment_method,
            'items'            => $items,
        ], $subscription->user);

        $order->subtotal       = 0;
        $order->shipping_fee   = 0;
        $order->total_amount   = 0;
        $order->payment_status = $subscription->payment_status;
        $order->save();

        return $order->fresh('items');
    }

    private function hasEnoughStock(CustomerSubscription $subscription): bool
    {
        foreach ($subscription->plan->items as $planItem) {
            $product = $planItem->product;

            if ($product === null) {
                return false;
            }

            if ($product->totalAvailableQuantity() < (float) $planItem->quantity) {
                return false;
            }
        }

        return true;
    }

    public function findNextDeliveryDate(Carbon $from, array $weekdays, Carbon $endDate): ?Carbon
    {
        $weekdays = array_map('intval', $weekdays);
        $cursor   = $from->copy()->startOfDay();

        for ($i = 0; $i < 14; $i++) {
            if ($cursor->gt($endDate)) {
                return null;
            }

            if (in_array($cursor->dayOfWeek, $weekdays, true)) {
                return $cursor->copy();
            }

            $cursor->addDay();
        }

        return null;
    }

    private function generateSubscriptionCode(): string
    {
        $prefix = 'SUB-' . now()->format('Ymd') . '-';

        $todayCount = CustomerSubscription::whereDate('created_at', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $todayCount, 3, '0', STR_PAD_LEFT);
    }
}

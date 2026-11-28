<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Throwable;

class GenerateSubscriptionOrders extends Command
{
    protected $signature = 'subscription:generate-orders';

    protected $description = 'Quét các lịch giao gói định kỳ đến hạn hôm nay và tự động sinh đơn hàng con';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $this->info('[' . now()->toDateTimeString() . '] Bắt đầu tiến trình sinh đơn hàng gói định kỳ...');

        try {
            $createdOrders = $subscriptionService->generateDueOrders();
            $count = count($createdOrders);

            if ($count === 0) {
                $this->info('Không có lịch giao gói định kỳ nào đến hạn hôm nay.');
                return Command::SUCCESS;
            }

            $this->info("Đã sinh thành công {$count} đơn hàng con:");
            foreach ($createdOrders as $order) {
                $this->line(" - Đơn #{$order->ma_don} (Gói #{$order->subscription_id})");
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Lỗi khi sinh đơn hàng định kỳ: ' . $e->getMessage());
            report($e);
            return Command::FAILURE;
        }
    }
}

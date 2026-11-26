<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Throwable;

class GenerateSubscriptionOrders extends Command
{
    // Tên và chữ ký lệnh trên terminal
    protected $signature = 'subscription:generate-orders';

    // Mô tả ngắn gọn chức năng của lệnh
    protected $description = 'Quét các gói định kỳ đến hạn giao hôm nay và tự động sinh đơn hàng con';

    // Thực thi lệnh console
    public function handle(SubscriptionService $subscriptionService): int
    {
        $this->info('[' . now()->toDateTimeString() . '] Bắt đầu tiến trình sinh đơn hàng gói định kỳ...');

        try {
            $createdOrders = $subscriptionService->generateDueOrders();
            $count = count($createdOrders);

            if ($count === 0) {
                $this->info('Không có gói định kỳ nào đến lịch giao hôm nay.');
                return Command::SUCCESS;
            }

            $this->info("Đã sinh thành công {$count} đơn hàng con cho kỳ giao hôm nay:");
            foreach ($createdOrders as $order) {
                $this->line(" - Đơn hàng #{$order->order_code} (Gói #{$order->subscription_id}) - Khách hàng ID: {$order->user_id}");
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Lỗi khi sinh đơn hàng định kỳ: ' . $e->getMessage());
            report($e);
            return Command::FAILURE;
        }
    }
}

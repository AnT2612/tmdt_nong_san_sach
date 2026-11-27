<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    // Bước 1 của FEFO: chọn ra danh sách các lô sẽ lấy hàng
    //
    // Ví dụ trong đề bài Tuần 2: Lô 1 còn 2kg (hạn 20/11), Lô 2 còn 10kg
    // (hạn 25/11), khách đặt 5kg. Kết quả trả về:
    // [ Lô 1 => 2kg , Lô 2 => 3kg ]
    public function planFefoAllocation(Product $product, float $requiredQuantity): array
    {
        // Lấy các lô còn bán được, sắp theo hạn sử dụng gần nhất (scope FEFO).
        $batches = $product->availableBatches()->get();

        $totalAvailable = (float) $batches->sum('quantity_available');

        if ($totalAvailable < $requiredQuantity) {
            throw new RuntimeException(
                "Sản phẩm {$product->name} chỉ còn {$totalAvailable} {$product->unit}, "
                . "không đủ để đáp ứng {$requiredQuantity} {$product->unit}."
            );
        }

        $allocation = [];
        $remaining  = $requiredQuantity;

        // Duyệt từng lô theo thứ tự hạn sử dụng gần nhất trước.
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $supply = (float) $batch->quantity_available;

            // Lấy tối đa bằng phần còn thiếu, không lấy vượt tồn của lô.
            $take = min($supply, $remaining);

            if ($take > 0) {
                $allocation[$batch->id] = round($take, 3);
                $remaining              = round($remaining - $take, 3);
            }
        }

        return $allocation;
    }

    // Bước 2 của FEFO: thực sự trừ kho cho từng lô đã chọn
    //
    // Với mỗi lô:
    // - khoá dòng lô lại (lockForUpdate) để 2 khách không cùng lúc
    // làm lô bị trừ âm
    // - cộng dồn số lượng đã đặt
    // - ghi một dòng thẻ kho loại export_order
    // - nếu lô về 0 thì đổi trạng thái sang depleted
    public function decreaseStock(array $allocation, int $orderId, User $staff): array
    {
        $result = [];

        foreach ($allocation as $batchId => $quantity) {
            $batch = Batch::whereKey($batchId)->lockForUpdate()->first();

            if ($batch === null) {
                throw new RuntimeException("Lô hàng #{$batchId} không còn tồn tại.");
            }

            if ((float) $batch->quantity_available < $quantity) {
                throw new RuntimeException(
                    "Lô {$batch->batch_code} không đủ hàng (còn {$batch->quantity_available}, cần {$quantity})."
                );
            }

            $balanceAfter = round((float) $batch->quantity_available - $quantity, 3);

            $batch->quantity_available = $balanceAfter;

            // Lô hết sạch thì đánh dấu đã cạn, không cho bán tiếp.
            if ($balanceAfter <= 0) {
                $batch->status = Batch::STATUS_DEPLETED;
            }

            $batch->save();

            InventoryTransaction::create([
                'batch_id'      => $batch->id,
                'type'          => InventoryTransaction::TYPE_EXPORT_ORDER,
                'quantity'      => $quantity,
                'balance_after' => $balanceAfter,
                'order_id'      => $orderId,
                'created_by'    => $staff->id,
                'note'          => 'Xuất kho tự động theo FEFO cho đơn hàng.',
            ]);

            $result[$batch->id] = [
                'quantity'      => $quantity,
                'balance_after' => $balanceAfter,
            ];
        }

        return $result;
    }

    // Xuất hủy / HAO HỤT
    //
    // Nhân viên kho chọn đúng Lô và số lượng bị hỏng, hệ thống trừ tồn
    // và ghi thẻ kho loại dispose để còn tính được tỷ lệ hao hụt
    public function disposeStock(
        Batch $batch,
        float $quantity,
        string $reason,
        User $staff,
        ?string $note = null
    ): InventoryTransaction {
        if ($quantity <= 0) {
            throw new RuntimeException('Số lượng xuất hủy phải lớn hơn 0.');
        }

        return DB::transaction(function () use ($batch, $quantity, $reason, $staff, $note) {
            $lockedBatch = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ((float) $lockedBatch->quantity_available < $quantity) {
                throw new RuntimeException(
                    "Lô {$lockedBatch->batch_code} chỉ còn {$lockedBatch->quantity_available}, "
                    . "không thể hủy {$quantity}."
                );
            }

            $balanceAfter = round((float) $lockedBatch->quantity_available - $quantity, 3);

            $lockedBatch->quantity_available = $balanceAfter;

            // Hết hàng do hủy thì đánh dấu đã xuất hủy toàn bộ.
            if ($balanceAfter <= 0) {
                $lockedBatch->status = Batch::STATUS_DISPOSED;
            }

            $lockedBatch->save();

            return InventoryTransaction::create([
                'batch_id'      => $lockedBatch->id,
                'type'          => InventoryTransaction::TYPE_DISPOSE,
                'quantity'      => $quantity,
                'balance_after' => $balanceAfter,
                'reason'        => $reason,
                'note'          => $note,
                'created_by'    => $staff->id,
            ]);
        });
    }

    // Nhập kho khi thu hoạch một lô
    // Ghi thẻ kho loại import và chuyển lô sang trạng thái available
    public function importStock(Batch $batch, User $staff): InventoryTransaction
    {
        $batch->status = Batch::STATUS_AVAILABLE;
        $batch->save();

        return InventoryTransaction::create([
            'batch_id'      => $batch->id,
            'type'          => InventoryTransaction::TYPE_IMPORT,
            'quantity'      => $batch->quantity_imported,
            'balance_after' => $batch->quantity_available,
            'created_by'    => $staff->id,
            'note'          => 'Nhập kho sau thu hoạch.',
        ]);
    }

    // Danh sách lô sắp hết hạn - dùng cho cảnh báo xả hàng (Flash Sale)
    public function batchesExpiringSoon(int $withinDays): Collection
    {
        return Batch::query()
            ->available()
            ->with('product')
            ->whereDate('expiry_date', '<=', now()->addDays($withinDays)->toDateString())
            ->fefoOrder()
            ->get();
    }

    // Tỷ lệ hao hụt của một khoảng thời gian
    //
    // Công thức: tổng xuất hủy / tổng nhập kho * 100
    // Đây là số liệu Admin dùng cho báo cáo thống kê
    public function lossRate(string $fromDate, string $toDate): array
    {
        $disposed = (float) InventoryTransaction::query()
            ->where('type', InventoryTransaction::TYPE_DISPOSE)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('quantity');

        $imported = (float) InventoryTransaction::query()
            ->where('type', InventoryTransaction::TYPE_IMPORT)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('quantity');

        return [
            'disposed'  => round($disposed, 3),
            'imported'  => round($imported, 3),
            'loss_rate' => $imported > 0 ? round(($disposed / $imported) * 100, 2) : 0.0,
        ];
    }
}

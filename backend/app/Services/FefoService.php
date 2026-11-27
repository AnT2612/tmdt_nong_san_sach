<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Batch;
use App\Models\BatchAlert;
use App\Models\LossRecord;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FefoService
{
    public function allocateAndDeduct(Order $order, ?array $items = null): void
    {
        DB::transaction(function () use ($order, $items) {
            if ($items !== null) {
                $order->orderDetails()->delete();
                $sourceLines = collect($items)->map(fn (array $item) => [
                    'product_id' => (int) $item['product_id'],
                    'so_luong'   => (float) $item['so_luong'],
                    'don_gia'    => isset($item['don_gia']) ? (float) $item['don_gia'] : null,
                ]);
            } else {
                $order->loadMissing('orderDetails.product');
                $sourceLines = $order->orderDetails->map(fn (OrderDetail $detail) => [
                    'detail'     => $detail,
                    'product_id' => (int) $detail->product_id,
                    'so_luong'   => (float) $detail->so_luong,
                    'don_gia'    => (float) $detail->don_gia,
                ]);
            }

            foreach ($sourceLines as $line) {
                $product = Product::find($line['product_id']);
                $need    = round((float) $line['so_luong'], 2);

                if ($product === null || $need <= 0) {
                    continue;
                }

                $batches = Batch::query()
                    ->where('product_id', $product->id)
                    ->whereIn('trang_thai', [Batch::STATUS_CON_HANG, Batch::STATUS_CAN_HAN])
                    ->where('so_luong_ton', '>', 0)
                    ->whereDate('ngay_het_han', '>=', now()->toDateString())
                    ->orderBy('ngay_het_han', 'asc')
                    ->orderBy('ngay_thu_hoach', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $totalAvailable = round((float) $batches->sum('so_luong_ton'), 2);

                if ($totalAvailable < $need) {
                    throw new InsufficientStockException('Số lượng tồn kho không đủ đáp ứng đơn hàng.');
                }

                if (isset($line['detail'])) {
                    $line['detail']->delete();
                }

                $remaining = $need;
                $basePrice = $line['don_gia'] ?? (float) $product->gia_ban_co_ban;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min((float) $batch->so_luong_ton, $remaining);
                    if ($take <= 0) {
                        continue;
                    }

                    $newStock = round((float) $batch->so_luong_ton - $take, 2);
                    $batch->so_luong_ton = $newStock;
                    if ($newStock <= 0) {
                        $batch->trang_thai = Batch::STATUS_HET_HANG;
                    }
                    $batch->save();

                    $unitPrice = $batch->gia_xa_hang !== null
                        ? (float) $batch->gia_xa_hang
                        : $basePrice;

                    OrderDetail::create([
                        'order_id'   => $order->id,
                        'product_id' => $product->id,
                        'batch_id'   => $batch->id,
                        'so_luong'   => round($take, 2),
                        'don_gia'    => $unitPrice,
                        'thanh_tien' => round($take * $unitPrice, 0),
                    ]);

                    $remaining = round($remaining - $take, 2);
                }
            }
        });
    }

    public function restoreOrderStock(Order $order): void
    {
        $order->loadMissing('orderDetails');

        foreach ($order->orderDetails as $detail) {
            if ($detail->batch_id === null) {
                continue;
            }

            $batch = Batch::whereKey($detail->batch_id)->lockForUpdate()->first();
            if ($batch === null) {
                continue;
            }

            $batch->so_luong_ton = round((float) $batch->so_luong_ton + (float) $detail->so_luong, 2);

            if ($batch->trang_thai === Batch::STATUS_HET_HANG && $batch->so_luong_ton > 0) {
                $days = $batch->daysUntilExpiry() ?? 99;
                $batch->trang_thai = $days < 0
                    ? Batch::STATUS_HET_HAN
                    : ($days <= 2 ? Batch::STATUS_CAN_HAN : Batch::STATUS_CON_HANG);
            }

            $batch->save();
        }
    }

    public function recordLoss(
        Batch $batch,
        float $quantity,
        string $reason,
        User $staff,
        ?string $image = null
    ): LossRecord {
        if ($quantity <= 0) {
            throw new RuntimeException('Số lượng xuất hủy phải lớn hơn 0.');
        }

        return DB::transaction(function () use ($batch, $quantity, $reason, $staff, $image) {
            $locked = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ((float) $locked->so_luong_ton < $quantity) {
                throw new RuntimeException(
                    "Lô {$locked->ma_lo} chỉ còn {$locked->so_luong_ton}, không thể xuất hủy {$quantity}."
                );
            }

            $locked->so_luong_ton     = round((float) $locked->so_luong_ton - $quantity, 2);
            $locked->so_luong_hao_hut = round((float) $locked->so_luong_hao_hut + $quantity, 2);

            if ($locked->so_luong_ton <= 0) {
                $locked->trang_thai = Batch::STATUS_HET_HANG;
            }

            $locked->save();

            return LossRecord::create([
                'batch_id'   => $locked->id,
                'so_luong'   => round($quantity, 2),
                'ly_do'      => $reason,
                'hinh_anh'   => $image,
                'created_by' => $staff->id,
                'created_at' => now(),
            ]);
        });
    }

    public function batchesExpiringSoon(int $withinDays = 2): Collection
    {
        return Batch::query()
            ->available()
            ->with('product')
            ->whereDate('ngay_het_han', '<=', now()->addDays($withinDays)->toDateString())
            ->fefoOrder()
            ->get();
    }

    public function scanAndCreateAlerts(int $withinDays = 2): int
    {
        $batches = $this->batchesExpiringSoon($withinDays);
        $created = 0;

        foreach ($batches as $batch) {
            if ($batch->trang_thai === Batch::STATUS_CON_HANG) {
                $batch->trang_thai = Batch::STATUS_CAN_HAN;
                $batch->save();
            }

            $exists = BatchAlert::where('batch_dang_mo', $batch->id)->exists();
            if (! $exists) {
                BatchAlert::create([
                    'batch_id'      => $batch->id,
                    'ngay_canh_bao' => now(),
                    'trang_thai'    => BatchAlert::STATUS_DANG_MO,
                    'batch_dang_mo' => $batch->id,
                ]);
                $created++;
            }
        }

        return $created;
    }

    public function lossRate(?string $fromDate = null, ?string $toDate = null): array
    {
        $imported = (float) Batch::query()->sum('so_luong_nhap');

        $lossQuery = LossRecord::query();
        if ($fromDate !== null && $toDate !== null) {
            $lossQuery->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate]);
        }
        $disposed = (float) $lossQuery->sum('so_luong');

        return [
            'disposed'  => round($disposed, 2),
            'imported'  => round($imported, 2),
            'loss_rate' => $imported > 0 ? round(($disposed / $imported) * 100, 2) : 0.0,
        ];
    }
}

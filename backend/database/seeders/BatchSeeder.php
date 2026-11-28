<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\BatchAlert;
use App\Models\Certificate;
use App\Models\FarmingLog;
use App\Models\LossRecord;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\QrGeneratorService;
use Illuminate\Database\Seeder;

class BatchSeeder extends Seeder
{
    public function run(): void
    {
        $farmStaff = User::whereHas('role', fn ($q) => $q->where('ten_vai_tro', Role::FARM_STAFF))->first();

        if ($farmStaff === null) {
            return;
        }

        $qrService = app(QrGeneratorService::class);
        $dateTag   = now()->format('Ymd');

        $templates = [
            // Bảng 5.1 Word: kịch bản kiểm thử FEFO đa nhánh trên Cà chua bi
            [
                'sku'            => 'CA-CHUA-BI',
                'ma_lo'          => "LO-{$dateTag}-001",
                'days_expiry'    => -1,
                'so_luong_nhap'  => 5,
                'so_luong_ton'   => 5,
                'so_luong_hh'    => 0,
                'trang_thai'     => Batch::STATUS_HET_HAN,
                'gia_xa_hang'    => null,
            ],
            [
                'sku'            => 'CA-CHUA-BI',
                'ma_lo'          => "LO-{$dateTag}-002",
                'days_expiry'    => 1,
                'so_luong_nhap'  => 5,
                'so_luong_ton'   => 2,
                'so_luong_hh'    => 1,
                'trang_thai'     => Batch::STATUS_CAN_HAN,
                'gia_xa_hang'    => 35000,
            ],
            [
                'sku'            => 'CA-CHUA-BI',
                'ma_lo'          => "LO-{$dateTag}-003",
                'days_expiry'    => 5,
                'so_luong_nhap'  => 15,
                'so_luong_ton'   => 15,
                'so_luong_hh'    => 0,
                'trang_thai'     => Batch::STATUS_CON_HANG,
                'gia_xa_hang'    => null,
            ],
            [
                'sku'            => 'CA-CHUA-BI',
                'ma_lo'          => "LO-{$dateTag}-004",
                'days_expiry'    => 4,
                'so_luong_nhap'  => 8,
                'so_luong_ton'   => 0,
                'so_luong_hh'    => 0,
                'trang_thai'     => Batch::STATUS_HET_HANG,
                'gia_xa_hang'    => null,
            ],
            // Các nông sản khác
            [
                'sku'            => 'RAU-MUONG',
                'ma_lo'          => "LO-{$dateTag}-005",
                'days_expiry'    => 2,
                'so_luong_nhap'  => 30,
                'so_luong_ton'   => 28,
                'so_luong_hh'    => 2,
                'trang_thai'     => Batch::STATUS_CAN_HAN,
                'gia_xa_hang'    => 9000,
            ],
            [
                'sku'            => 'CAI-NGOT',
                'ma_lo'          => "LO-{$dateTag}-006",
                'days_expiry'    => 4,
                'so_luong_nhap'  => 18,
                'so_luong_ton'   => 18,
                'so_luong_hh'    => 0,
                'trang_thai'     => Batch::STATUS_CON_HANG,
                'gia_xa_hang'    => null,
            ],
            [
                'sku'            => 'CA-ROT',
                'ma_lo'          => "LO-{$dateTag}-007",
                'days_expiry'    => 12,
                'so_luong_nhap'  => 25,
                'so_luong_ton'   => 25,
                'so_luong_hh'    => 0,
                'trang_thai'     => Batch::STATUS_CON_HANG,
                'gia_xa_hang'    => null,
            ],
            [
                'sku'            => 'DUA-LEO',
                'ma_lo'          => "LO-{$dateTag}-008",
                'days_expiry'    => 5,
                'so_luong_nhap'  => 15,
                'so_luong_ton'   => 14,
                'so_luong_hh'    => 1,
                'trang_thai'     => Batch::STATUS_CON_HANG,
                'gia_xa_hang'    => null,
            ],
            [
                'sku'            => 'HANH-LA',
                'ma_lo'          => "LO-{$dateTag}-009",
                'days_expiry'    => 3,
                'so_luong_nhap'  => 40,
                'so_luong_ton'   => 40,
                'so_luong_hh'    => 0,
                'trang_thai'     => Batch::STATUS_CON_HANG,
                'gia_xa_hang'    => null,
            ],
        ];

        foreach ($templates as $index => $item) {
            $product = Product::where('sku', $item['sku'])->first();

            if ($product === null) {
                continue;
            }

            $expiryDate  = now()->addDays($item['days_expiry'])->startOfDay();
            $harvestDate = $expiryDate->copy()->subDays(6);

            $batch = Batch::updateOrCreate(
                ['ma_lo' => $item['ma_lo']],
                [
                    'product_id'       => $product->id,
                    'ngay_thu_hoach'   => $harvestDate->toDateString(),
                    'ngay_het_han'     => $expiryDate->toDateString(),
                    'so_luong_nhap'    => $item['so_luong_nhap'],
                    'so_luong_ton'     => $item['so_luong_ton'],
                    'so_luong_hao_hut' => $item['so_luong_hh'],
                    'qr_code'          => url('/truy-xuat/' . $item['ma_lo']),
                    'trang_thai'       => $item['trang_thai'],
                    'gia_xa_hang'      => $item['gia_xa_hang'],
                    'created_by'       => $farmStaff->id,
                ]
            );

            $qrService->generateForBatch($batch);

            if ($item['so_luong_hh'] > 0 && $batch->lossRecords()->count() === 0) {
                LossRecord::create([
                    'batch_id'   => $batch->id,
                    'so_luong'   => $item['so_luong_hh'],
                    'ly_do'      => 'Dập nát trong quá trình phân loại sau thu hoạch',
                    'hinh_anh'   => null,
                    'created_by' => $farmStaff->id,
                    'created_at' => now(),
                ]);
            }

            if ($item['trang_thai'] === Batch::STATUS_CAN_HAN && $batch->alerts()->where('trang_thai', BatchAlert::STATUS_DANG_MO)->count() === 0) {
                BatchAlert::create([
                    'batch_id'      => $batch->id,
                    'handled_by'    => null,
                    'ngay_canh_bao' => now(),
                    'trang_thai'    => BatchAlert::STATUS_DANG_MO,
                    'batch_dang_mo' => $batch->id,
                ]);
            }

            if ($index < 3 && $batch->farmingLogs()->count() === 0) {
                $this->seedLogsAndCertificate($batch, $farmStaff);
            }
        }
    }

    private function seedLogsAndCertificate(Batch $batch, User $farmStaff): void
    {
        $planted = $batch->ngay_thu_hoach->copy()->subDays(25);

        $logs = [
            ['hanh_dong' => 'Gieo hạt',         'mo_ta' => 'Gieo hạt trong nhà lưới, giá thể hữu cơ đã khử trùng.', 'offset' => 0],
            ['hanh_dong' => 'Tưới nhỏ giọt',    'mo_ta' => 'Tưới tự động 2 lần/ngày bằng nước giếng kiểm định.',   'offset' => 6],
            ['hanh_dong' => 'Bón phân hữu cơ',  'mo_ta' => 'Bón phân trùn quế, không dùng phân bón hóa học.',     'offset' => 14],
            ['hanh_dong' => 'Kiểm tra sâu bệnh','mo_ta' => 'Cây sinh trưởng khỏe, không phát hiện sâu hại.',      'offset' => 20],
            ['hanh_dong' => 'Thu hoạch',        'mo_ta' => 'Thu hoạch vào sáng sớm và chuyển kho mát ngay.',      'offset' => 25],
        ];

        foreach ($logs as $log) {
            FarmingLog::create([
                'batch_id'            => $batch->id,
                'farm_staff_id'       => $farmStaff->id,
                'hanh_dong'           => $log['hanh_dong'],
                'mo_ta'               => $log['mo_ta'],
                'hinh_anh_minh_chung' => null,
                'thoi_gian'           => $planted->copy()->addDays($log['offset']),
            ]);
        }

        if ($batch->certificates()->count() === 0) {
            Certificate::create([
                'batch_id'        => $batch->id,
                'uploaded_by'     => $farmStaff->id,
                'ten_chung_nhan'  => 'Chứng nhận VietGAP & Kiểm nghiệm dư lượng nông dược',
                'file_minh_chung' => url('/truy-xuat/' . $batch->ma_lo),
                'ngay_cap'        => $batch->ngay_thu_hoach->toDateString(),
                'ngay_het_han'    => $batch->ngay_thu_hoach->copy()->addYear()->toDateString(),
                'created_at'      => now(),
            ]);
        }
    }
}

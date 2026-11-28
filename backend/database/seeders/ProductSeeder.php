<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['ten_danh_muc' => 'Rau ăn lá', 'slug' => 'rau-an-la', 'mo_ta' => 'Cải, muống, mồng tơi...'],
            ['ten_danh_muc' => 'Củ quả',    'slug' => 'cu-qua',    'mo_ta' => 'Cà rốt, khoai tây, cà chua...'],
            ['ten_danh_muc' => 'Trái cây',  'slug' => 'trai-cay',  'mo_ta' => 'Trái cây theo mùa'],
            ['ten_danh_muc' => 'Gia vị',    'slug' => 'gia-vi',    'mo_ta' => 'Hành, tỏi, ớt, sả...'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['trang_thai' => true])
            );
        }

        $categoryIds = Category::pluck('id', 'slug');

        $products = [
            [
                'category_id'    => $categoryIds['rau-an-la'],
                'ten_san_pham'   => 'Rau muống hữu cơ',
                'sku'            => 'RAU-MUONG',
                'don_vi_tinh'    => 'bó',
                'gia_ban_co_ban' => 12000,
                'chung_nhan'     => 'VietGAP',
                'mo_ta'          => 'Rau muống trồng tại Nông trại Củ Chi theo chuẩn VietGAP, không thuốc trừ sâu hóa học.',
            ],
            [
                'category_id'    => $categoryIds['rau-an-la'],
                'ten_san_pham'   => 'Cải ngọt',
                'sku'            => 'CAI-NGOT',
                'don_vi_tinh'    => 'kg',
                'gia_ban_co_ban' => 25000,
                'chung_nhan'     => 'VietGAP',
                'mo_ta'          => 'Cải ngọt non thu hoạch trong ngày tại Nông trại Củ Chi.',
            ],
            [
                'category_id'    => $categoryIds['cu-qua'],
                'ten_san_pham'   => 'Cà chua bi',
                'sku'            => 'CA-CHUA-BI',
                'don_vi_tinh'    => 'kg',
                'gia_ban_co_ban' => 45000,
                'chung_nhan'     => 'VietGAP',
                'mo_ta'          => 'Cà chua bi hữu cơ trồng trong nhà lưới, vị ngọt thanh tự nhiên.',
            ],
            [
                'category_id'    => $categoryIds['cu-qua'],
                'ten_san_pham'   => 'Cà rốt Đà Lạt',
                'sku'            => 'CA-ROT',
                'don_vi_tinh'    => 'kg',
                'gia_ban_co_ban' => 32000,
                'chung_nhan'     => 'VietGAP',
                'mo_ta'          => 'Cà rốt tươi thu hoạch đạt chuẩn, bảo quản kho mát.',
            ],
            [
                'category_id'    => $categoryIds['trai-cay'],
                'ten_san_pham'   => 'Dưa leo baby',
                'sku'            => 'DUA-LEO',
                'don_vi_tinh'    => 'kg',
                'gia_ban_co_ban' => 28000,
                'chung_nhan'     => 'USDA Organic',
                'mo_ta'          => 'Dưa leo giòn ngọt, có thể ăn sống trực tiếp.',
            ],
            [
                'category_id'    => $categoryIds['gia-vi'],
                'ten_san_pham'   => 'Hành lá',
                'sku'            => 'HANH-LA',
                'don_vi_tinh'    => 'bó',
                'gia_ban_co_ban' => 8000,
                'chung_nhan'     => 'VietGAP',
                'mo_ta'          => 'Hành lá tươi thơm thu hoạch sáng sớm.',
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'slug'       => Str::slug($product['ten_san_pham']),
                    'trang_thai' => true,
                ])
            );
        }
    }
}

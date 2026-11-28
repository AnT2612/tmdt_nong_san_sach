<?php

return [
    'plans' => [
        [
            'code'                 => 'goi-rau-gia-dinh',
            'ten_goi'              => 'Gói Rau Gia Đình',
            'mo_ta'                => 'Gói rau củ tươi cho gia đình 3-4 người, giao Thứ 3 và Thứ 6 hằng tuần trong 30 ngày.',
            'gia_goi'              => 450000,
            'thoi_han_ngay'        => 30,
            'ngay_giao_trong_tuan' => '3,6',
            'items' => [
                ['sku' => 'RAU-MUONG',  'so_luong' => 2],
                ['sku' => 'CA-CHUA-BI', 'so_luong' => 1],
                ['sku' => 'CAI-NGOT',   'so_luong' => 1],
            ],
        ],
        [
            'code'                 => 'goi-rau-van-phong',
            'ten_goi'              => 'Gói Rau Văn Phòng',
            'mo_ta'                => 'Gói rau sạch nhỏ gọn, giao mỗi Thứ 2 hằng tuần trong 30 ngày, phù hợp người độc thân.',
            'gia_goi'              => 280000,
            'thoi_han_ngay'        => 30,
            'ngay_giao_trong_tuan' => '2',
            'items' => [
                ['sku' => 'RAU-MUONG', 'so_luong' => 1],
                ['sku' => 'HANH-LA',   'so_luong' => 1],
                ['sku' => 'DUA-LEO',   'so_luong' => 0.5],
            ],
        ],
    ],
];

<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            Role::ADMIN      => 'Quản trị viên toàn quyền hệ thống',
            Role::SALES      => 'Nhân viên bán hàng & CSKH',
            Role::FARM_STAFF => 'Nhân viên nông trại & quản lý lô hàng',
            Role::CUSTOMER   => 'Khách hàng mua lẻ và gói định kỳ',
        ];

        $roleIds = [];
        foreach ($roles as $name => $desc) {
            $role = Role::updateOrCreate(
                ['ten_vai_tro' => $name],
                ['mo_ta' => $desc]
            );
            $roleIds[$name] = $role->id;
        }

        $accounts = [
            [
                'ho_ten'        => 'Trần Quốc Khánh',
                'email'         => 'admin@nongsan.vn',
                'so_dien_thoai' => '0901234561',
                'role_id'       => $roleIds[Role::ADMIN],
                'diem_tich_luy' => 0,
            ],
            [
                'ho_ten'        => 'Trần Thanh An',
                'email'         => 'sales@nongsan.vn',
                'so_dien_thoai' => '0901234563',
                'role_id'       => $roleIds[Role::SALES],
                'diem_tich_luy' => 0,
            ],
            [
                'ho_ten'        => 'Nguyễn Thanh Liêm',
                'email'         => 'nongtrai@nongsan.vn',
                'so_dien_thoai' => '0901234564',
                'role_id'       => $roleIds[Role::FARM_STAFF],
                'diem_tich_luy' => 0,
            ],
            [
                'ho_ten'        => 'Nguyễn Văn Đoàn',
                'email'         => 'kho@nongsan.vn',
                'so_dien_thoai' => '0901234562',
                'role_id'       => $roleIds[Role::FARM_STAFF],
                'diem_tich_luy' => 0,
            ],
            [
                'ho_ten'        => 'Đào Quốc Đạt',
                'email'         => 'khachhang@nongsan.vn',
                'so_dien_thoai' => '0901234565',
                'role_id'       => $roleIds[Role::CUSTOMER],
                'diem_tich_luy' => 120,
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'role_id'       => $account['role_id'],
                    'ho_ten'        => $account['ho_ten'],
                    'so_dien_thoai' => $account['so_dien_thoai'],
                    'mat_khau'      => '12345678',
                    'diem_tich_luy' => $account['diem_tich_luy'],
                    'trang_thai'    => true,
                ]
            );

            if ($account['email'] === 'khachhang@nongsan.vn') {
                Address::updateOrCreate(
                    ['user_id' => $user->id, 'mac_dinh' => true],
                    [
                        'dia_chi'    => '65 Huỳnh Thúc Kháng, Phường Bến Nghé',
                        'quan_huyen' => 'Quận 1',
                        'tinh_thanh' => 'TP. Hồ Chí Minh',
                        'vi_do'      => 10.771595,
                        'kinh_do'    => 106.701356,
                        'mac_dinh'   => true,
                    ]
                );
            }
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Tuan5ApiScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function loginAs(string $email): string
    {
        $res = $this->postJson('/api/v1/auth/login', [
            'email'    => $email,
            'mat_khau' => '12345678',
        ]);

        $res->assertStatus(200);

        return (string) $res->json('data.token');
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_table_5_4_scenarios_tc01_to_tc10(): void
    {
        $customerToken  = $this->loginAs('khachhang@nongsan.vn');
        $farmStaffToken = $this->loginAs('nongtrai@nongsan.vn');
        $salesToken     = $this->loginAs('sales@nongsan.vn');

        $tomato = Product::where('sku', 'CA-CHUA-BI')->firstOrFail();

        // TC-01: Customer đặt 10 kg Cà chua bi -> tách 3 kg lô -002 (het_hang) và 7 kg lô -003 (còn 8 kg)
        $tc01 = $this->postJson('/api/v1/orders', [
            'ten_nguoi_nhan'  => 'Đào Quốc Đạt',
            'so_dien_thoai'   => '0901234565',
            'dia_chi_giao'    => '65 Huỳnh Thúc Kháng, Quận 1, TP. Hồ Chí Minh',
            'cong_thanh_toan' => 'cod',
            'items'           => [
                ['product_id' => $tomato->id, 'quantity' => 10],
            ],
        ], $this->bearer($customerToken));

        $tc01->assertStatus(201)
            ->assertJsonPath('success', true);

        $orderId = (int) $tc01->json('data.order.id');
        $details = $tc01->json('data.order.order_details');
        $this->assertCount(2, $details);
        $this->assertEquals(3.0, (float) $details[0]['so_luong']);
        $this->assertStringEndsWith('-002', $details[0]['batch']['ma_lo']);
        $this->assertEquals(7.0, (float) $details[1]['so_luong']);
        $this->assertStringEndsWith('-003', $details[1]['batch']['ma_lo']);

        $batch001 = Batch::where('ma_lo', 'like', '%-001')->firstOrFail();
        $batch002 = Batch::where('ma_lo', 'like', '%-002')->firstOrFail();
        $batch003 = Batch::where('ma_lo', 'like', '%-003')->firstOrFail();

        $this->assertEquals(0.0, (float) $batch002->so_luong_ton);
        $this->assertSame(Batch::STATUS_HET_HANG, $batch002->trang_thai);
        $this->assertEquals(8.0, (float) $batch003->so_luong_ton);

        // TC-02: Đặt 9 kg khi tổng tồn hợp lệ còn 8 kg -> 422 message chuẩn + hoàn tác
        $ordersBefore = Order::count();
        $tc02 = $this->postJson('/api/v1/orders', [
            'ten_nguoi_nhan'  => 'Đào Quốc Đạt',
            'so_dien_thoai'   => '0901234565',
            'dia_chi_giao'    => '65 Huỳnh Thúc Kháng, Quận 1, TP. Hồ Chí Minh',
            'cong_thanh_toan' => 'cod',
            'items'           => [
                ['product_id' => $tomato->id, 'quantity' => 9],
            ],
        ], $this->bearer($customerToken));

        $tc02->assertStatus(422)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Số lượng tồn kho không đủ đáp ứng đơn hàng.',
            ]);
        $this->assertSame($ordersBefore, Order::count());
        $this->assertEquals(8.0, (float) $batch003->fresh()->so_luong_ton);

        // TC-03: GET /api/v1/batches sau TC-01 kiểm tra lô quá hạn -001 vẫn tồn 5 kg
        $tc03 = $this->getJson('/api/v1/batches', $this->bearer($farmStaffToken));
        $tc03->assertStatus(200);
        $b001Row = collect($tc03->json('data'))->firstWhere('id', $batch001->id);
        $this->assertNotNull($b001Row);
        $this->assertEquals(5.0, (float) $b001Row['so_luong_ton']);

        // TC-04: Farm Staff gửi thiếu ngay_het_han -> 422 JSON, không tạo bản ghi
        $batchesBefore = Batch::count();
        $tc04 = $this->postJson('/api/v1/batches', [
            'product_id'     => $tomato->id,
            'ngay_thu_hoach' => now()->toDateString(),
            'so_luong_nhap'  => 10,
        ], $this->bearer($farmStaffToken));
        $tc04->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['errors' => ['ngay_het_han']]);
        $this->assertSame($batchesBefore, Batch::count());

        // TC-05: Farm Staff tạo lô hợp lệ -> 201 + qr_code trỏ về /truy-xuat/{ma_lo}
        $tc05 = $this->postJson('/api/v1/batches', [
            'product_id'     => $tomato->id,
            'ngay_thu_hoach' => now()->toDateString(),
            'ngay_het_han'   => now()->addDays(6)->toDateString(),
            'so_luong_nhap'  => 12,
        ], $this->bearer($farmStaffToken));
        $tc05->assertStatus(201);
        $createdBatch = $tc05->json('data.batch');
        $this->assertStringContainsString('/truy-xuat/' . $createdBatch['ma_lo'], $createdBatch['qr_code']);

        // TC-06: GET /api/v1/batches/9999 -> 404 JSON
        $tc06 = $this->getJson('/api/v1/batches/9999', $this->bearer($farmStaffToken));
        $tc06->assertStatus(404)
            ->assertJsonPath('status', 'error');

        // TC-07: POST /api/v1/batches không gửi token -> 401 JSON
        $tc07 = $this->post('/api/v1/batches', [
            'product_id' => $tomato->id,
        ]);
        $tc07->assertStatus(401)
            ->assertJsonPath('status', 'error');

        // TC-08: PUT /api/v1/batches/{id} bằng tài khoản Customer -> 403 JSON
        $tc08 = $this->putJson("/api/v1/batches/{$createdBatch['id']}", [
            'ngay_thu_hoach' => now()->toDateString(),
            'ngay_het_han'   => now()->addDays(7)->toDateString(),
            'so_luong_nhap'  => 12,
        ], $this->bearer($customerToken));
        $tc08->assertStatus(403)
            ->assertJsonPath('status', 'error');

        // TC-09: PATCH /api/v1/orders/{id}/status Sales đổi trạng thái đơn hợp lệ -> 200
        $tc09 = $this->patchJson("/api/v1/orders/{$orderId}/status", [
            'trang_thai' => Order::STATUS_DA_DUYET,
        ], $this->bearer($salesToken));
        $tc09->assertStatus(200)
            ->assertJsonPath('data.trang_thai', Order::STATUS_DA_DUYET);

        // TC-10: Customer thêm rồi DELETE /api/v1/cart/items/{id} -> 200 dòng giỏ hàng bị xóa
        $addCart = $this->postJson('/api/v1/cart/items', [
            'product_id' => $tomato->id,
            'quantity'   => 2,
        ], $this->bearer($customerToken));
        $addCart->assertStatus(201);
        $cartItemId = (int) $addCart->json('data.item.id');

        $tc10 = $this->deleteJson("/api/v1/cart/items/{$cartItemId}", [], $this->bearer($customerToken));
        $tc10->assertStatus(200)
            ->assertJsonPath('data.deleted_item_id', $cartItemId)
            ->assertJsonCount(0, 'data.items');
    }
}

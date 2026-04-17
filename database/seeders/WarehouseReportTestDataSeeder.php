<?php

namespace Database\Seeders;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Warehouse\InventoryMovementType;
use App\Models\Customer;
use App\Models\District;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Province;
use App\Models\Ward;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WarehouseReportTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = 1;

        // -------------------------------------------------------
        // 0. Đảm bảo Organization tồn tại
        // Schema: id, name (not null), code (unique, not null), product_field (smallint, not null)
        // -------------------------------------------------------
        $org = Organization::find($organizationId);
        if (!$org) {
            Organization::insert([
                'id'            => $organizationId,
                'name'          => 'Công ty Pharmavet',
                'code'          => 'PHV',
                'product_field' => 1, // bắt buộc, không null
                'disable'       => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // -------------------------------------------------------
        // 0.5 Đảm bảo dữ liệu địa lý tồn tại
        // Schema provinces: code (char(2)), name, code_name, division_type
        // Schema districts: code (char(5)), name, code_name, division_type, province_id
        // Schema wards: code (char(5)), name, code_name, division_type, district_id
        // -------------------------------------------------------
        $province = Province::first();
        if (!$province) {
            $province = Province::create([
                'name'          => 'Hà Nội',
                'code'          => '01',
                'code_name'     => 'ha_noi',
                'division_type' => 'Thành phố Trung ương',
            ]);
        }

        $district = District::where('province_id', $province->id)->first();
        if (!$district) {
            $district = District::create([
                'name'          => 'Ba Đình',
                'code'          => '001',
                'code_name'     => 'ba_dinh',
                'division_type' => 'Quận',
                'province_id'   => $province->id,
                'province_code' => $province->code,
            ]);
        }

        $ward = Ward::where('district_id', $district->id)->first();
        if (!$ward) {
            $ward = Ward::create([
                'name'          => 'Phúc Xá',
                'code'          => '00001',
                'code_name'     => 'phuc_xa',
                'division_type' => 'Phường',
                'district_id'   => $district->id,
                'district_code' => $district->code,
            ]);
        }

        // -------------------------------------------------------
        // 1. Tạo Kho hàng
        // Schema warehouses (database.md): province_id nullable, district_id nullable,
        // ward_id nullable, address nullable, phone KHÔNG CÓ trong schema database.md
        // unique [organization_id, code]
        // -------------------------------------------------------
        $warehouses = [];
        $warehouseNames = ['Kho Hà Nội', 'Kho HCM', 'Kho Đà Nẵng'];
        for ($i = 1; $i <= 3; $i++) {
            $warehouses[] = Warehouse::firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'code'            => "KHO-$i",
                ],
                [
                    'name'        => $warehouseNames[$i - 1],
                    'is_active'   => true,
                    'province_id' => $province->id,
                    'district_id' => $district->id,
                    'ward_id'     => $ward->id,
                    'address'     => 'Địa chỉ mẫu ' . $i . ', ' . $province->name,
                ]
            );
        }

        // -------------------------------------------------------
        // 2. Tạo Sản phẩm
        // Schema products: sku (unique, not null), name, weight
        // -------------------------------------------------------
        $products = [];
        $productNames = ['Thuốc Bổ A', 'Vitamin C', 'Khoáng Chất D3', 'Men Vi Sinh', 'Canxi Pro'];
        for ($i = 1; $i <= 5; $i++) {
            $products[] = Product::firstOrCreate(
                [
                    'sku'             => "PHARMA-$i",
                    'organization_id' => $organizationId,
                ],
                [
                    'name'       => $productNames[$i - 1],
                    'cost_price' => 50000 * $i,
                    'sale_price' => 80000 * $i,
                    'weight'     => 300,
                ]
            );
        }

        // -------------------------------------------------------
        // 3. Ma trận kho (product_warehouse)
        // Schema: product_id, warehouse_id, quantity (int), pending_quantity (int)
        // KHÔNG có cột actual_quantity
        // -------------------------------------------------------
        foreach ($warehouses as $wh) {
            foreach ($products as $prod) {
                $qty     = rand(50, 500);
                $pending = rand(5, min(50, $qty));
                ProductWarehouse::updateOrCreate(
                    [
                        'warehouse_id' => $wh->id,
                        'product_id'   => $prod->id,
                    ],
                    [
                        'quantity'         => $qty,
                        'pending_quantity' => $pending,
                    ]
                );
            }
        }

        // -------------------------------------------------------
        // 4. Biến động kho (inventory_movements)
        // Schema: organization_id, warehouse_id, product_id, movement_type,
        //         quantity_change, occurred_at, actor_id (nullable)
        // -------------------------------------------------------
        $startOfMonth = Carbon::now()->startOfMonth();
        $today = Carbon::now();
        $diffDays = max(1, $today->diffInDays($startOfMonth));

        for ($i = 0; $i < 40; $i++) {
            $importDate = clone $startOfMonth;
            $importDate->addDays(rand(0, $diffDays));
            if ($importDate->gt($today)) {
                $importDate = clone $today;
            }

            // Nhập kho
            InventoryMovement::create([
                'organization_id' => $organizationId,
                'warehouse_id'    => $warehouses[array_rand($warehouses)]->id,
                'product_id'      => $products[array_rand($products)]->id,
                'movement_type'   => InventoryMovementType::IN->value,
                'quantity_change' => rand(20, 200),
                'occurred_at'     => $importDate,
            ]);

            $exportDate = (clone $importDate)->addHours(rand(1, 8));
            if ($exportDate->gt($today)) {
                $exportDate = clone $today;
            }

            // Xuất kho
            InventoryMovement::create([
                'organization_id' => $organizationId,
                'warehouse_id'    => $warehouses[array_rand($warehouses)]->id,
                'product_id'      => $products[array_rand($products)]->id,
                'movement_type'   => InventoryMovementType::OUT->value,
                'quantity_change' => -rand(5, 80),
                'occurred_at'     => $exportDate,
            ]);
        }

        // -------------------------------------------------------
        // 5. Doanh số đơn hàng (orders)
        // Schema customers: username (not null), customer_type (not null)
        // Schema orders: customer_id, code (unique), status, total_amount
        // -------------------------------------------------------
        $customer = Customer::where('organization_id', $organizationId)->first();
        if (!$customer) {
            $customer = Customer::create([
                'username'         => 'Khách Mẫu Kho',
                'phone'            => '0987654321',
                'organization_id'  => $organizationId,
                'customer_type'    => 1, // bắt buộc, not null
                'interaction_status' => 1,
                'province_id'      => $province->id,
                'district_id'      => $district->id,
                'ward_id'          => $ward->id,
            ]);
        }

        for ($i = 0; $i < 25; $i++) {
            $orderDate = clone $startOfMonth;
            $orderDate->addDays(rand(0, $diffDays));
            if ($orderDate->gt($today)) {
                $orderDate = clone $today;
            }

            $updatedAt = (clone $orderDate)->addHours(rand(1, 6));

            // Dùng DB::table để tránh hook boot() của Order kiểm tra AccountingPeriod
            \Illuminate\Support\Facades\DB::table('orders')->insertOrIgnore([
                'organization_id' => $organizationId,
                'customer_id'     => $customer->id,
                'warehouse_id'    => $warehouses[array_rand($warehouses)]->id,
                'code'            => 'WH-TEST-' . strtoupper(Str::random(6)),
                'status'          => OrderStatus::COMPLETED->value,
                'total_amount'    => rand(200000, 8000000),
                'discount'        => 0,
                'created_at'      => $orderDate,
                'updated_at'      => $updatedAt,
            ]);
        }

        $this->command->info('✓ Seeded: ' . count($warehouses) . ' kho, ' . count($products) . ' sản phẩm, 40 biến động kho, 25 đơn hàng mẫu.');
    }
}

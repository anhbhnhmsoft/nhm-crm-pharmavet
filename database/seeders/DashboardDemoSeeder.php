<?php

namespace Database\Seeders;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Organization\FundTransactionStatus;
use App\Common\Constants\Organization\FundTransactionType;
use App\Models\Customer;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $organization = Organization::query()->first();
            if (!$organization) {
                return;
            }

            $staffIds = User::query()
                ->where('organization_id', $organization->id)
                ->pluck('id')
                ->values();

            if ($staffIds->isEmpty()) {
                return;
            }

            $products = $this->seedProducts((int) $organization->id);
            $this->seedCustomersAndOrders((int) $organization->id, $staffIds->all(), $products);

            if ((bool) $organization->is_foreign) {
                $this->seedFundTransactions((int) $organization->id, (int) $staffIds->first());
            }
        });
    }

    private function seedProducts(int $organizationId): array
    {
        $catalog = [
            ['name' => 'Kháng sinh thú y A', 'price' => 180000],
            ['name' => 'Vitamin tổng hợp B', 'price' => 125000],
            ['name' => 'Thuốc sát trùng C', 'price' => 95000],
            ['name' => 'Men tiêu hóa D', 'price' => 135000],
            ['name' => 'Thuốc bổ gan E', 'price' => 165000],
            ['name' => 'Canxi tăng trưởng F', 'price' => 148000],
            ['name' => 'Khoáng chất G', 'price' => 112000],
            ['name' => 'Thảo dược hô hấp H', 'price' => 175000],
        ];

        $products = [];
        foreach ($catalog as $index => $item) {
            $sku = sprintf('DSH-%d-%03d', $organizationId, $index + 1);
            $product = Product::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'organization_id' => $organizationId,
                    'name' => $item['name'],
                    'unit' => 'hộp',
                    'sale_price' => $item['price'],
                    'cost_price' => max(1000, (int) round($item['price'] * 0.68)),
                    'quantity' => 500,
                    'type' => 'pharma',
                ]
            );
            $products[] = $product;
        }

        return $products;
    }

    private function seedCustomersAndOrders(int $organizationId, array $staffIds, array $products): void
    {
        $sources = ['facebook_ads', 'google_ads', 'website', 'zalo', 'manual'];
        $currentMonthStart = now()->startOfMonth();

        for ($dayOffset = 0; $dayOffset < 28; $dayOffset++) {
            $baseDate = $currentMonthStart->copy()->addDays($dayOffset)->setTime(rand(8, 20), rand(0, 59));

            for ($n = 1; $n <= 3; $n++) {
                $seedNo = ($dayOffset * 3) + $n;
                $source = $sources[$seedNo % count($sources)];
                $customerType = match (true) {
                    $seedNo % 6 === 0 => CustomerType::NEW_DUPLICATE->value,
                    $seedNo % 9 === 0 => CustomerType::OLD_CUSTOMER->value,
                    default => CustomerType::NEW->value,
                };

                $assignedStaffId = $seedNo % 7 === 0 ? null : $staffIds[$seedNo % count($staffIds)];
                $customer = Customer::query()->updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'source_id' => 'dashboard_seed_customer_' . $seedNo,
                    ],
                    [
                        'username' => 'Dashboard Lead ' . $seedNo,
                        'phone' => '0907' . str_pad((string) (100000 + $seedNo), 6, '0', STR_PAD_LEFT),
                        'email' => 'dashboard_lead_' . $seedNo . '@example.com',
                        'address' => 'Seed Address ' . $seedNo,
                        'source' => $source,
                        'source_detail' => 'campaign_' . (($seedNo % 4) + 1),
                        'customer_type' => $customerType,
                        'assigned_staff_id' => $assignedStaffId,
                        'interaction_status' => 1,
                        'created_at' => $baseDate,
                        'updated_at' => $baseDate,
                    ]
                );

                if ($seedNo % 4 !== 0) {
                    $this->upsertOrderForCustomer($organizationId, $customer->id, $assignedStaffId, $seedNo, $baseDate, $products);
                }
            }
        }
    }

    private function upsertOrderForCustomer(
        int $organizationId,
        int $customerId,
        ?int $staffId,
        int $seedNo,
        Carbon $baseDate,
        array $products
    ): void {
        $status = match (true) {
            $seedNo % 10 === 0 => OrderStatus::CANCELLED->value,
            $seedNo % 7 === 0 => OrderStatus::SHIPPING->value,
            $seedNo % 5 === 0 => OrderStatus::CONFIRMED->value,
            default => OrderStatus::COMPLETED->value,
        };

        $lineCount = ($seedNo % 3) + 1;
        $pickedProducts = collect($products)->shuffle()->take($lineCount)->values();
        $subtotal = 0;
        $lines = [];

        foreach ($pickedProducts as $index => $product) {
            $qty = (($seedNo + $index) % 3) + 1;
            $price = (float) ($product->sale_price ?? 100000);
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;
            $lines[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'price' => $price,
                'total' => $lineTotal,
            ];
        }

        $shippingFee = $seedNo % 3 === 0 ? 25000 : 35000;
        $discount = $seedNo % 6 === 0 ? 50000 : 0;
        $total = max(0, $subtotal + $shippingFee - $discount);
        $createdAt = $baseDate->copy()->addHours(2);
        $code = sprintf('ORD-DSH-%d-%05d', $organizationId, $seedNo);

        $order = Order::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'code' => $code],
            [
                'customer_id' => $customerId,
                'status' => $status,
                'total_amount' => $total,
                'discount' => $discount,
                'shipping_fee' => $shippingFee,
                'deposit' => $seedNo % 8 === 0 ? 100000 : 0,
                'created_by' => $staffId,
                'updated_by' => $staffId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]
        );

        OrderItem::query()->where('order_id', $order->id)->delete();
        foreach ($lines as $line) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'price' => $line['price'],
                'total' => $line['total'],
            ]);
        }
    }

    private function seedFundTransactions(int $organizationId, int $actorId): void
    {
        $fund = Fund::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'balance' => 0,
                'currency' => 'VND',
                'fund_type' => 'cash',
                'is_locked' => false,
            ]
        );

        $openingBalance = 120000000;
        $fund->update(['balance' => $openingBalance]);

        $balance = $openingBalance;
        $start = now()->startOfMonth();

        for ($i = 1; $i <= 18; $i++) {
            $isDeposit = $i % 3 !== 0;
            $amount = $isDeposit ? rand(2500000, 12000000) : rand(1200000, 7000000);
            $type = $isDeposit ? FundTransactionType::DEPOSIT->value : FundTransactionType::WITHDRAW->value;
            $status = $i % 7 === 0 ? FundTransactionStatus::PENDING->value : FundTransactionStatus::COMPLETED->value;
            $txDate = $start->copy()->addDays($i)->setTime(rand(8, 18), rand(0, 59));

            if ($status === FundTransactionStatus::COMPLETED->value) {
                $balance += $isDeposit ? $amount : -$amount;
            }

            FundTransaction::query()->updateOrCreate(
                ['fund_id' => $fund->id, 'transaction_code' => sprintf('FTX-DSH-%d-%03d', $organizationId, $i)],
                [
                    'type' => $type,
                    'transaction_date' => $txDate->toDateString(),
                    'balance_after' => max(0, $balance),
                    'amount' => $amount,
                    'counterparty_name' => $isDeposit ? 'Nop quy demo' : 'Chi quy demo',
                    'currency' => 'VND',
                    'exchange_rate' => 1,
                    'amount_base' => $amount,
                    'description' => $isDeposit ? 'Thu demo dashboard' : 'Chi demo dashboard',
                    'purpose' => $isDeposit ? 'thu_tien_ban_hang' : 'chi_van_hanh',
                    'note' => 'Dashboard seeder',
                    'status' => $status,
                    'updated_by' => $actorId,
                    'created_at' => $txDate,
                    'updated_at' => $txDate,
                ]
            );
        }

        $fund->update(['balance' => max(0, $balance)]);
    }
}


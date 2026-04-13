<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Customer;
use App\Models\User;
use App\Common\Constants\Order\OrderStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BadDebtTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();
        $customer = Customer::first();
        $user = User::first();

        if (!$org || !$customer || !$user) {
            $this->command->error('Vui lòng đảm bảo đã có ít nhất 1 Tổ chức, 1 Khách hàng và 1 User trong database.');
            return;
        }

        $monthsToOpen = [];
        for ($i = 0; $i <= 4; $i++) {
            $d = Carbon::now()->subMonths($i);
            $monthsToOpen[] = [
                'month' => $d->month,
                'year' => $d->year,
            ];
        }

        $originalStates = [];

        foreach ($monthsToOpen as $item) {
            $key = $item['month'] . '-' . $item['year'];
            $period = AccountingPeriod::where([
                'organization_id' => $org->id,
                'month' => $item['month'],
                'year' => $item['year']
            ])->first();

            $originalStates[$key] = $period ? $period->closed_at : null;

            AccountingPeriod::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'month' => $item['month'],
                    'year' => $item['year']
                ],
                [
                    'closed_at' => null, // Tạm thời MỞ kỳ kế toán
                    'note' => 'Hệ thống tự động mở tạm thời để seeding 50 đơn nợ xấu'
                ]
            );
        }

        $this->command->info('Đang tạo 50 đơn hàng nợ xấu...');
        for ($i = 1; $i <= 50; $i++) {
            $daysAgo = rand(31, 120); // Ngẫu nhiên từ 31 đến 120 ngày
            $createdAt = Carbon::now()->subDays($daysAgo);
            $totalAmount = rand(500, 5000) * 1000; // 500k đến 5tr
            $paidAmount = rand(0, 5) * 100000; // 0 đến 500k đã trả

            Order::create([
                'organization_id' => $org->id,
                'customer_id' => $customer->id,
                'code' => 'ORD-BAD-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => OrderStatus::COMPLETED->value,
                'total_amount' => $totalAmount,
                'collect_amount' => $totalAmount,
                'amount_recived_from_customer' => min($paidAmount, $totalAmount),
                'created_by' => $user->id,
                'created_at' => $createdAt,
            ]);
        }

        // 4. Khóa lại trạng thái ban đầu của các kỳ kế toán
        foreach ($monthsToOpen as $item) {
            $key = $item['month'] . '-' . $item['year'];
            AccountingPeriod::where([
                'organization_id' => $org->id,
                'month' => $item['month'],
                'year' => $item['year']
            ])->update([
                'closed_at' => $originalStates[$key] ?? null
            ]);
        }

        $this->command->info('Đã tạo xong 50 đơn nợ xấu và hoàn trả trạng thái Khóa/Mở cho các kỳ kế toán.');
    }
}

<?php

namespace Database\Seeders;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\Organization\FundLockAction;
use App\Common\Constants\Organization\FundLockScope;
use App\Common\Constants\Organization\FundTransactionStatus;
use App\Common\Constants\Organization\FundTransactionType;
use App\Models\Fund;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AccountingShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $organization = Organization::query()->first();
            if (!$organization) {
                return;
            }

            $actor = User::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();

            if (!$actor) {
                return;
            }

            $this->clearAccountingData((int) $organization->id);

            $exchangeRateIds = $this->seedExchangeRates((int) $organization->id, (int) $actor->id);
            $this->seedAccountingPeriods((int) $organization->id, (int) $actor->id);
            $funds = $this->seedFundsAndTransactions((int) $organization->id, (int) $actor->id);
            $this->seedFundLockRulesAndAudits($funds, (int) $actor->id);
            $this->seedRevenuesAndExpenses((int) $organization->id, (int) $actor->id);
            $this->seedReconciliations((int) $organization->id, (int) $actor->id, $exchangeRateIds);
            $this->seedFinancialSummaries((int) $organization->id);
        });
    }

    private function clearAccountingData(int $organizationId): void
    {
        $fundIds = DB::table('funds')->where('organization_id', $organizationId)->pluck('id');
        $transactionIds = $fundIds->isEmpty()
            ? collect()
            : DB::table('fund_transactions')->whereIn('fund_id', $fundIds)->pluck('id');

        if ($transactionIds->isNotEmpty()) {
            DB::table('fund_transaction_attachments')->whereIn('fund_transaction_id', $transactionIds)->delete();
        }

        if ($fundIds->isNotEmpty()) {
            DB::table('fund_lock_audits')->whereIn('fund_id', $fundIds)->delete();
            DB::table('fund_lock_rules')->whereIn('fund_id', $fundIds)->delete();
            DB::table('fund_transactions')->whereIn('fund_id', $fundIds)->delete();
            DB::table('funds')->whereIn('id', $fundIds)->delete();
        }

        DB::table('expenses')->where('organization_id', $organizationId)->delete();
        DB::table('revenues')->where('organization_id', $organizationId)->delete();
        DB::table('reconciliations')->where('organization_id', $organizationId)->delete();
        DB::table('financial_summaries')->where('organization_id', $organizationId)->delete();
        DB::table('exchange_rates')->where('organization_id', $organizationId)->delete();
        DB::table('accounting_periods')->where('organization_id', $organizationId)->delete();

        Storage::disk('public')->deleteDirectory('fund-transactions/demo');
    }

    private function seedExchangeRates(int $organizationId, int $actorId): array
    {
        $ids = [];
        $start = now()->startOfMonth()->subMonth();

        for ($day = 0; $day < 55; $day++) {
            $rateDate = $start->copy()->addDays($day)->toDateString();

            $ids[] = DB::table('exchange_rates')->insertGetId([
                'organization_id' => $organizationId,
                'rate_date' => $rateDate,
                'from_currency' => 'VND',
                'to_currency' => 'USD',
                'rate' => 25200 + ($day % 5) * 10,
                'source' => 'manual',
                'note' => 'Seeded USD rate',
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('exchange_rates')->insert([
                'organization_id' => $organizationId,
                'rate_date' => $rateDate,
                'from_currency' => 'VND',
                'to_currency' => 'EUR',
                'rate' => 27300 + ($day % 4) * 12,
                'source' => 'manual',
                'note' => 'Seeded EUR rate',
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function seedAccountingPeriods(int $organizationId, int $actorId): void
    {
        $now = now();
        $previous = $now->copy()->subMonth();

        DB::table('accounting_periods')->insert([
            [
                'organization_id' => $organizationId,
                'month' => $previous->month,
                'year' => $previous->year,
                'closed_at' => $previous->copy()->endOfMonth()->setTime(23, 59, 59),
                'closed_by' => $actorId,
                'note' => 'Ky da khoa',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $organizationId,
                'month' => $now->month,
                'year' => $now->year,
                'closed_at' => null,
                'closed_by' => null,
                'note' => 'Ky dang mo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function seedFundsAndTransactions(int $organizationId, int $actorId): array
    {
        $fundPayloads = [
            ['fund_type' => 'cash', 'currency' => 'VND', 'name' => 'Quy tien mat'],
            ['fund_type' => 'bank', 'currency' => 'VND', 'name' => 'Quy ngan hang'],
            ['fund_type' => 'other', 'currency' => 'USD', 'name' => 'Quy ngoai te'],
        ];

        $funds = [];
        foreach ($fundPayloads as $payload) {
            /** @var Fund $fund */
            $fund = Fund::query()->create([
                'organization_id' => $organizationId,
                'balance' => 0,
                'currency' => $payload['currency'],
                'fund_type' => $payload['fund_type'],
                'is_locked' => false,
            ]);

            $this->seedFundTransactions((int) $fund->id, $payload['name'], $payload['currency'], $actorId);
            $fund->refresh();
            $funds[] = $fund;
        }

        return $funds;
    }

    private function seedFundTransactions(int $fundId, string $fundName, string $currency, int $actorId): void
    {
        $openedAt = now()->startOfMonth()->subDays(20);
        $balance = 0;
        $txNo = 1;

        for ($i = 0; $i < 32; $i++) {
            $txDate = $openedAt->copy()->addDays($i);
            $isDeposit = $i % 4 !== 0;
            $status = $i % 9 === 0 ? FundTransactionStatus::PENDING->value : FundTransactionStatus::COMPLETED->value;
            $type = $isDeposit ? FundTransactionType::DEPOSIT->value : FundTransactionType::WITHDRAW->value;
            $amount = $currency === 'USD'
                ? rand(120, 1200)
                : rand(1200000, 18000000);

            if ($status === FundTransactionStatus::COMPLETED->value) {
                $balance += $isDeposit ? $amount : -$amount;
                if ($balance < 0) {
                    $balance = 0;
                }
            }

            $txId = DB::table('fund_transactions')->insertGetId([
                'fund_id' => $fundId,
                'type' => $type,
                'transaction_code' => sprintf('FTX-%d-%04d', $fundId, $txNo++),
                'transaction_id' => sprintf('BANKREF-%d-%d', $fundId, $i + 1),
                'transaction_date' => $txDate->toDateString(),
                'balance_after' => $balance,
                'amount' => $amount,
                'counterparty_name' => $isDeposit ? 'Khach hang doi tac' : 'Nha cung cap',
                'currency' => $currency,
                'exchange_rate' => $currency === 'USD' ? 25250 : 1,
                'amount_base' => $currency === 'USD' ? $amount * 25250 : $amount,
                'description' => ($isDeposit ? 'Thu' : 'Chi') . ' tu ' . $fundName,
                'purpose' => $isDeposit ? 'thu_tien_ban_hang' : 'chi_van_hanh',
                'note' => 'Du lieu ke toan demo',
                'status' => $status,
                'updated_by' => $actorId,
                'created_at' => $txDate->copy()->setTime(rand(8, 17), rand(0, 59)),
                'updated_at' => $txDate->copy()->setTime(rand(8, 17), rand(0, 59)),
            ]);

            if ($i % 7 === 0) {
                $this->seedTransactionAttachments($txId, $fundId, $txDate, $actorId);
            }
        }

        DB::table('funds')->where('id', $fundId)->update(['balance' => $balance]);
    }

    private function seedTransactionAttachments(int $transactionId, int $fundId, Carbon $txDate, int $actorId): void
    {
        $versions = rand(1, 2);
        for ($version = 1; $version <= $versions; $version++) {
            $path = sprintf(
                'fund-transactions/demo/fund_%d/tx_%d_v%d.txt',
                $fundId,
                $transactionId,
                $version
            );

            Storage::disk('public')->put(
                $path,
                sprintf('Chung tu demo transaction #%d version %d', $transactionId, $version)
            );

            DB::table('fund_transaction_attachments')->insert([
                'fund_transaction_id' => $transactionId,
                'version' => $version,
                'file_path' => $path,
                'original_name' => basename($path),
                'mime_type' => 'text/plain',
                'file_size' => Storage::disk('public')->size($path),
                'uploaded_by' => $actorId,
                'uploaded_at' => $txDate->copy()->setTime(18, 0)->addMinutes($version),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedFundLockRulesAndAudits(array $funds, int $actorId): void
    {
        $targetUserId = User::query()->orderBy('id')->value('id');
        $targetTeamId = Schema::hasTable('teams') ? DB::table('teams')->orderBy('id')->value('id') : null;

        foreach ($funds as $fund) {
            $rules = [
                ['action' => FundLockAction::ADD->value, 'scope_type' => FundLockScope::GLOBAL->value, 'user_id' => null, 'team_id' => null, 'is_locked' => false],
                ['action' => FundLockAction::EDIT->value, 'scope_type' => FundLockScope::USER->value, 'user_id' => $targetUserId, 'team_id' => null, 'is_locked' => true],
                ['action' => FundLockAction::DELETE->value, 'scope_type' => FundLockScope::TEAM->value, 'user_id' => null, 'team_id' => $targetTeamId, 'is_locked' => true],
            ];

            foreach ($rules as $rule) {
                if ($rule['scope_type'] === FundLockScope::TEAM->value && !$rule['team_id']) {
                    continue;
                }

                DB::table('fund_lock_rules')->insert([
                    'fund_id' => $fund->id,
                    'action' => $rule['action'],
                    'scope_type' => $rule['scope_type'],
                    'user_id' => $rule['user_id'],
                    'team_id' => $rule['team_id'],
                    'is_locked' => $rule['is_locked'],
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('fund_lock_audits')->insert([
                    'fund_id' => $fund->id,
                    'action' => $rule['action'],
                    'is_locked' => $rule['is_locked'],
                    'scope_type' => $rule['scope_type'],
                    'target_user_id' => $rule['user_id'],
                    'target_team_id' => $rule['team_id'],
                    'metadata_json' => json_encode(['seeded' => true, 'reason' => 'Accounting demo setup']),
                    'changed_by' => $actorId,
                    'changed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedRevenuesAndExpenses(int $organizationId, int $actorId): void
    {
        $start = now()->startOfMonth()->subMonth();
        $orderIds = Schema::hasTable('orders')
            ? DB::table('orders')->where('organization_id', $organizationId)->limit(200)->pluck('id')->all()
            : [];

        for ($i = 0; $i < 50; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $revenueAmount = rand(1500000, 24000000);

            DB::table('revenues')->insert([
                'organization_id' => $organizationId,
                'revenue_date' => $date,
                'description' => 'Doanh thu ban hang ngay ' . Carbon::parse($date)->format('d/m'),
                'amount' => $revenueAmount,
                'note' => 'Auto seeded for accounting showcase',
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $expenseRows = rand(1, 2);
            for ($j = 0; $j < $expenseRows; $j++) {
                $category = collect([
                    ExpenseCategory::OPERATIONAL->value,
                    ExpenseCategory::MARKETING->value,
                    ExpenseCategory::FINANCIAL->value,
                    ExpenseCategory::OTHER->value,
                    ExpenseCategory::SHIPPING_AUTO->value,
                ])->random();

                $quantity = rand(1, 5);
                $unitPrice = rand(200000, 3000000);
                $amount = $quantity * $unitPrice;

                DB::table('expenses')->insert([
                    'organization_id' => $organizationId,
                    'expense_date' => $date,
                    'category' => $category,
                    'description' => 'Chi phi van hanh ngay ' . Carbon::parse($date)->format('d/m'),
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'amount' => $amount,
                    'attachments' => json_encode([]),
                    'order_id' => !empty($orderIds) ? $orderIds[array_rand($orderIds)] : null,
                    'reconciliation_id' => null,
                    'note' => 'Auto seeded for accounting showcase',
                    'created_by' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedReconciliations(int $organizationId, int $actorId, array $exchangeRateIds): void
    {
        $orderIds = Schema::hasTable('orders')
            ? DB::table('orders')->where('organization_id', $organizationId)->orderBy('id', 'desc')->limit(40)->pluck('id')->all()
            : [];

        for ($i = 1; $i <= 18; $i++) {
            $date = now()->subDays(20 - $i)->toDateString();
            $shipping = rand(18000, 70000);
            $storage = rand(0, 15000);
            $cod = rand(300000, 5000000);
            $failed = $i % 6 === 0 ? rand(50000, 250000) : 0;

            DB::table('reconciliations')->insert([
                'organization_id' => $organizationId,
                'reconciliation_date' => $date,
                'order_id' => !empty($orderIds) ? $orderIds[array_rand($orderIds)] : null,
                'ghn_order_code' => 'GHN-DEMO-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'ghn_to_name' => 'Nguoi nhan demo ' . $i,
                'ghn_to_phone' => '0901' . str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
                'ghn_to_address' => 'Dia chi giao hang demo #' . $i,
                'ghn_status_label' => 'delivered',
                'ghn_created_at' => Carbon::parse($date)->setTime(9, 10),
                'ghn_updated_at' => Carbon::parse($date)->setTime(16, 35),
                'ghn_items' => json_encode([['name' => 'Demo item', 'quantity' => rand(1, 3)]]),
                'ghn_payment_type_id' => 2,
                'ghn_weight' => rand(200, 1200),
                'ghn_content' => 'Hang thu y',
                'ghn_required_note' => 'CHOXEMHANGKHONGTHU',
                'ghn_employee_note' => 'Don doi soat demo',
                'ghn_cod_failed_amount' => $failed,
                'cod_amount' => $cod,
                'shipping_fee' => $shipping,
                'storage_fee' => $storage,
                'total_fee' => $shipping + $storage,
                'exchange_rate_id' => !empty($exchangeRateIds) ? $exchangeRateIds[array_rand($exchangeRateIds)] : null,
                'converted_amount' => max(0, $cod - $shipping - $storage - $failed),
                'status' => collect([
                    ReconciliationStatus::PENDING->value,
                    ReconciliationStatus::CONFIRMED->value,
                    ReconciliationStatus::PAID->value,
                ])->random(),
                'note' => 'Auto seeded for accounting showcase',
                'created_by' => $actorId,
                'confirmed_by' => $i % 3 === 0 ? $actorId : null,
                'confirmed_at' => $i % 3 === 0 ? Carbon::parse($date)->setTime(18, 0) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedFinancialSummaries(int $organizationId): void
    {
        $start = now()->startOfMonth()->subMonth();

        for ($i = 0; $i < 50; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $gross = rand(12000000, 120000000);
            $discount = rand(200000, 5000000);
            $returns = rand(0, 3000000);
            $netRevenue = max(0, $gross - $discount - $returns);
            $cogs = (int) round($netRevenue * rand(45, 72) / 100);
            $grossProfit = max(0, $netRevenue - $cogs);
            $otherRevenue = rand(0, 3500000);
            $expense = rand(1500000, 22000000);
            $netProfit = $grossProfit + $otherRevenue - $expense;

            DB::table('financial_summaries')->insert([
                'organization_id' => $organizationId,
                'date' => $date,
                'orders_count' => rand(12, 130),
                'gross_revenue' => $gross,
                'discounts' => $discount,
                'returns_value' => $returns,
                'net_revenue' => $netRevenue,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'other_revenues' => $otherRevenue,
                'total_expenses' => $expense,
                'net_profit' => $netProfit,
                'gross_margin_rate' => $netRevenue > 0 ? round($grossProfit / $netRevenue * 100, 2) : 0,
                'net_margin_rate' => $netRevenue > 0 ? round($netProfit / $netRevenue * 100, 2) : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

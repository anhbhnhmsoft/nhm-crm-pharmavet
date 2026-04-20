<?php

namespace Database\Seeders;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Organization\FundLockAction;
use App\Common\Constants\Organization\FundLockScope;
use App\Common\Constants\Organization\FundTransactionStatus;
use App\Common\Constants\Organization\FundTransactionType;
use App\Common\Constants\Order\GhnOrderStatus;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Shipping\ProviderShipping;
use App\Common\Constants\Team\TeamType;
use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Models\Fund;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccountingShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->first();

        if (! $organization) {
            return;
        }

        $this->seedForOrganizationId((int) $organization->id);
    }

    public function seedForUserEmail(string $email): void
    {
        $actor = User::query()
            ->where('email', $email)
            ->first();

        if (! $actor) {
            return;
        }

        $this->seedForOrganizationId((int) $actor->organization_id, (int) $actor->id);
    }

    public function seedForOrganizationId(int $organizationId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($organizationId, $actorId): void {
            $organization = Organization::query()->find($organizationId);

            if (! $organization) {
                return;
            }

            $actor = $actorId
                ? User::query()->whereKey($actorId)->first()
                : null;

            if (! $actor || (int) $actor->organization_id !== (int) $organization->id) {
                $actor = User::query()
                    ->where('organization_id', $organization->id)
                    ->orderBy('id')
                    ->first();
            }

            if (! $actor) {
                return;
            }

            $this->clearAccountingData((int) $organization->id);

            $exchangeRateIds = $this->seedExchangeRates((int) $organization->id, (int) $actor->id);
            $this->seedAccountingPeriods((int) $organization->id, (int) $actor->id);
            $funds = $this->seedFundsAndTransactions((int) $organization->id, (int) $actor->id);
            $this->seedFundLockRulesAndAudits($funds, (int) $actor->id);
            $saleFilterOrderIds = $this->seedSaleFilterHierarchy((int) $organization->id, (int) $actor->id);
            $this->seedRevenuesAndExpenses((int) $organization->id, (int) $actor->id);
            $this->seedReconciliations((int) $organization->id, (int) $actor->id, $exchangeRateIds, $saleFilterOrderIds);
            $this->seedFollowOrderShowcaseCases((int) $organization->id, (int) $actor->id, $exchangeRateIds);
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
            ? DB::table('orders')
                ->where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->limit(200)
                ->pluck('id')
                ->all()
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

    private function seedSaleFilterHierarchy(int $organizationId, int $actorId): array
    {
        if (! Schema::hasTable('teams') || ! Schema::hasTable('users')) {
            return [];
        }

        $leaderAlphaTeamA = $this->upsertSaleTeam(
            organizationId: $organizationId,
            code: sprintf('SALE-FLT-A1-%d', $organizationId),
            name: 'Sale Filter Team A1',
            description: 'Seeder generated sale filter team A1'
        );
        $leaderAlphaTeamB = $this->upsertSaleTeam(
            organizationId: $organizationId,
            code: sprintf('SALE-FLT-A2-%d', $organizationId),
            name: 'Sale Filter Team A2',
            description: 'Seeder generated sale filter team A2'
        );
        $leaderBetaTeam = $this->upsertSaleTeam(
            organizationId: $organizationId,
            code: sprintf('SALE-FLT-B1-%d', $organizationId),
            name: 'Sale Filter Team B1',
            description: 'Seeder generated sale filter team B1'
        );

        $leaderAlphaId = $this->upsertSaleUser(
            organizationId: $organizationId,
            actorId: $actorId,
            username: sprintf('sale_leader_filter_a_%d', $organizationId),
            email: sprintf('sale_leader_filter_a_%d@example.com', $organizationId),
            name: 'Truong nhom sale filter A',
            position: UserPosition::LEADER->value,
            teamId: $leaderAlphaTeamA
        );
        $leaderBetaId = $this->upsertSaleUser(
            organizationId: $organizationId,
            actorId: $actorId,
            username: sprintf('sale_leader_filter_b_%d', $organizationId),
            email: sprintf('sale_leader_filter_b_%d@example.com', $organizationId),
            name: 'Truong nhom sale filter B',
            position: UserPosition::LEADER->value,
            teamId: $leaderBetaTeam
        );

        $saleAlphaA1Id = $this->upsertSaleUser(
            organizationId: $organizationId,
            actorId: $actorId,
            username: sprintf('sale_filter_a1_1_%d', $organizationId),
            email: sprintf('sale_filter_a1_1_%d@example.com', $organizationId),
            name: 'Sale Filter A1-1',
            position: UserPosition::STAFF->value,
            teamId: $leaderAlphaTeamA
        );
        $saleAlphaA2Id = $this->upsertSaleUser(
            organizationId: $organizationId,
            actorId: $actorId,
            username: sprintf('sale_filter_a1_2_%d', $organizationId),
            email: sprintf('sale_filter_a1_2_%d@example.com', $organizationId),
            name: 'Sale Filter A1-2',
            position: UserPosition::STAFF->value,
            teamId: $leaderAlphaTeamA
        );
        $saleAlphaB1Id = $this->upsertSaleUser(
            organizationId: $organizationId,
            actorId: $actorId,
            username: sprintf('sale_filter_a2_1_%d', $organizationId),
            email: sprintf('sale_filter_a2_1_%d@example.com', $organizationId),
            name: 'Sale Filter A2-1',
            position: UserPosition::STAFF->value,
            teamId: $leaderAlphaTeamB
        );
        $saleBetaB1Id = $this->upsertSaleUser(
            organizationId: $organizationId,
            actorId: $actorId,
            username: sprintf('sale_filter_b1_1_%d', $organizationId),
            email: sprintf('sale_filter_b1_1_%d@example.com', $organizationId),
            name: 'Sale Filter B1-1',
            position: UserPosition::STAFF->value,
            teamId: $leaderBetaTeam
        );

        $this->syncUserTeams($leaderAlphaId, [$leaderAlphaTeamA, $leaderAlphaTeamB]);
        $this->syncUserTeams($leaderBetaId, [$leaderBetaTeam]);
        $this->syncUserTeams($saleAlphaA1Id, [$leaderAlphaTeamA]);
        $this->syncUserTeams($saleAlphaA2Id, [$leaderAlphaTeamA]);
        $this->syncUserTeams($saleAlphaB1Id, [$leaderAlphaTeamB]);
        $this->syncUserTeams($saleBetaB1Id, [$leaderBetaTeam]);

        return $this->seedSaleFilterOrders($organizationId, [
            [
                'code' => sprintf('ORD-SF-A1-01-O%d', $organizationId),
                'created_by' => $saleAlphaA1Id,
                'warehouse_slot' => 0,
                'days_ago' => 7,
                'total_amount' => 820000,
                'deposit' => 200000,
            ],
            [
                'code' => sprintf('ORD-SF-A1-02-O%d', $organizationId),
                'created_by' => $saleAlphaA2Id,
                'warehouse_slot' => 1,
                'days_ago' => 6,
                'total_amount' => 910000,
            ],
            [
                'code' => sprintf('ORD-SF-A2-01-O%d', $organizationId),
                'created_by' => $saleAlphaB1Id,
                'warehouse_slot' => 2,
                'days_ago' => 5,
                'total_amount' => 1030000,
                'deposit' => 250000,
            ],
            [
                'code' => sprintf('ORD-SF-A2-02-O%d', $organizationId),
                'created_by' => $saleAlphaB1Id,
                'warehouse_slot' => 0,
                'days_ago' => 4,
                'total_amount' => 1140000,
            ],
            [
                'code' => sprintf('ORD-SF-B1-01-O%d', $organizationId),
                'created_by' => $saleBetaB1Id,
                'warehouse_slot' => 1,
                'days_ago' => 3,
                'total_amount' => 1250000,
                'deposit' => 300000,
            ],
            [
                'code' => sprintf('ORD-SF-B1-02-O%d', $organizationId),
                'created_by' => $saleBetaB1Id,
                'warehouse_slot' => 2,
                'days_ago' => 2,
                'total_amount' => 1370000,
                'deposit' => 150000,
            ],
        ]);
    }

    private function upsertSaleTeam(int $organizationId, string $code, string $name, string $description): int
    {
        $existingTeamId = DB::table('teams')
            ->where('code', $code)
            ->value('id');

        $payload = [
            'organization_id' => $organizationId,
            'name' => $name,
            'type' => TeamType::SALE->value,
            'description' => $description,
            'updated_at' => now(),
            'deleted_at' => null,
        ];

        if ($existingTeamId) {
            DB::table('teams')
                ->where('id', $existingTeamId)
                ->update($payload);

            return (int) $existingTeamId;
        }

        return (int) DB::table('teams')->insertGetId([
            ...$payload,
            'code' => $code,
            'created_at' => now(),
        ]);
    }

    private function upsertSaleUser(
        int $organizationId,
        int $actorId,
        string $username,
        string $email,
        string $name,
        int $position,
        int $teamId
    ): int {
        $existingUserId = DB::table('users')
            ->where('username', $username)
            ->value('id');

        $payload = [
            'organization_id' => $organizationId,
            'email' => $email,
            'name' => $name,
            'role' => UserRole::SALE->value,
            'position' => $position,
            'team_id' => $teamId,
            'disable' => false,
            'updated_by' => $actorId,
            'updated_at' => now(),
            'deleted_at' => null,
        ];

        if ($existingUserId) {
            DB::table('users')
                ->where('id', $existingUserId)
                ->update([
                    ...$payload,
                    'password' => Hash::make('Test12345678@'),
                ]);

            return (int) $existingUserId;
        }

        return (int) DB::table('users')->insertGetId([
            ...$payload,
            'username' => $username,
            'password' => Hash::make('Test12345678@'),
            'created_by' => $actorId,
            'created_at' => now(),
        ]);
    }

    private function syncUserTeams(int $userId, array $teamIds): void
    {
        if (! Schema::hasTable('user_team')) {
            return;
        }

        foreach (collect($teamIds)->filter()->unique()->all() as $teamId) {
            DB::table('user_team')->insertOrIgnore([
                'user_id' => $userId,
                'team_id' => $teamId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedSaleFilterOrders(int $organizationId, array $definitions): array
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('warehouses')) {
            return [];
        }

        $warehouseIds = DB::table('warehouses')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($warehouseIds === []) {
            return [];
        }

        $customerId = $this->resolveShowcaseCustomerId($organizationId);

        if (! $customerId) {
            return [];
        }

        $orderIds = [];

        foreach ($definitions as $index => $definition) {
            $warehouseId = $warehouseIds[$definition['warehouse_slot'] % count($warehouseIds)];
            $createdAt = now()->subDays((int) $definition['days_ago'])->setTime(10 + ($index % 4), 20);

            $orderIds[] = $this->upsertShowcaseOrder(
                organizationId: $organizationId,
                customerId: $customerId,
                warehouseId: (int) $warehouseId,
                code: (string) $definition['code'],
                createdBy: (int) $definition['created_by'],
                totalAmount: (int) $definition['total_amount'],
                deposit: (int) ($definition['deposit'] ?? 0),
                createdAt: $createdAt
            );
        }

        return array_values(array_unique(array_filter($orderIds)));
    }

    private function upsertShowcaseOrder(
        int $organizationId,
        int $customerId,
        int $warehouseId,
        string $code,
        int $createdBy,
        int $totalAmount,
        int $deposit,
        Carbon $createdAt
    ): int {
        $existingOrderId = DB::table('orders')
            ->where('code', $code)
            ->value('id');

        $shippingFee = 30000;
        $collectAmount = max(0, ($totalAmount + $shippingFee) - $deposit);

        $payload = [
            'organization_id' => $organizationId,
            'customer_id' => $customerId,
            'warehouse_id' => $warehouseId,
            'status' => OrderStatus::COMPLETED->value,
            'total_amount' => $totalAmount,
            'discount' => 0,
            'shipping_fee' => $shippingFee,
            'deposit' => $deposit,
            'shipping_method' => ProviderShipping::GHN->value,
            'provider_shipping' => ProviderShipping::GHN->value,
            'ghn_order_code' => 'GHN-' . $code,
            'ghn_status' => 'delivered',
            'ghn_required_note' => 'CHOXEMHANGKHONGTHU',
            'collect_amount' => $collectAmount,
            'care_updated_at' => $createdAt->copy()->addHour(),
            'care_by_id' => $createdBy,
            'is_printed' => false,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
            'updated_at' => $createdAt->copy()->addMinutes(25),
            'deleted_at' => null,
        ];

        if ($existingOrderId) {
            DB::table('orders')
                ->where('id', $existingOrderId)
                ->update($payload);

            return (int) $existingOrderId;
        }

        return (int) DB::table('orders')->insertGetId([
            ...$payload,
            'code' => $code,
            'created_at' => $createdAt,
        ]);
    }

    private function seedReconciliations(int $organizationId, int $actorId, array $exchangeRateIds, array $preferredOrderIds = []): void
    {
        $orderIds = $this->resolveReconciliationOrderIds($organizationId, $actorId, $preferredOrderIds);
        $preferredOrderIds = collect($preferredOrderIds)
            ->filter()
            ->map(fn ($orderId) => (int) $orderId)
            ->unique()
            ->values()
            ->all();

        for ($i = 1; $i <= 18; $i++) {
            $date = now()->subDays(20 - $i)->toDateString();
            $shipping = rand(18000, 70000);
            $storage = rand(0, 15000);
            $cod = rand(300000, 5000000);
            $failed = $i % 6 === 0 ? rand(50000, 250000) : 0;
            $orderId = $preferredOrderIds[$i - 1] ?? (! empty($orderIds) ? $orderIds[array_rand($orderIds)] : null);
            $status = collect([
                ReconciliationStatus::PENDING->value,
                ReconciliationStatus::CONFIRMED->value,
                ReconciliationStatus::PAID->value,
            ])->random();
            $confirmedAt = in_array($status, [
                ReconciliationStatus::CONFIRMED->value,
                ReconciliationStatus::PAID->value,
            ], true)
                ? Carbon::parse($date)->setTime(18, 0)
                : null;
            $isInternalReconciled = in_array($status, [
                ReconciliationStatus::CONFIRMED->value,
                ReconciliationStatus::PAID->value,
            ], true) && $i % 4 === 0;

            DB::table('reconciliations')->insert([
                'organization_id' => $organizationId,
                'reconciliation_date' => $date,
                'order_id' => $orderId,
                'ghn_order_code' => 'GHN-DEMO-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'ghn_to_name' => 'Nguoi nhan demo ' . $i,
                'ghn_to_phone' => '0901' . str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
                'ghn_to_address' => 'Dia chi giao hang demo #' . $i,
                'ghn_status_label' => 'delivered',
                'ghn_created_at' => Carbon::parse($date)->setTime(9, 10),
                'ghn_updated_at' => Carbon::parse($date)->setTime(16, 35),
                'ghn_items' => $this->buildReconciliationGhnItems($orderId),
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
                'status' => $status,
                'note' => 'Auto seeded for accounting showcase',
                'is_internal_reconciled' => $isInternalReconciled,
                'created_by' => $actorId,
                'confirmed_by' => $confirmedAt ? $actorId : null,
                'confirmed_at' => $confirmedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function buildReconciliationGhnItems(?int $orderId): string
    {
        if ($orderId) {
            $orderItems = DB::table('order_items')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('order_items.order_id', $orderId)
                ->select('products.name', 'order_items.quantity', 'order_items.price')
                ->get()
                ->map(fn ($item) => [
                    'name' => (string) $item->name,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->price,
                ])
                ->values()
                ->all();

            if (!empty($orderItems)) {
                return json_encode($orderItems, JSON_UNESCAPED_UNICODE);
            }
        }

        return json_encode([
            [
                'name' => 'Demo item',
                'quantity' => rand(1, 3),
                'price' => 0,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function seedFollowOrderShowcaseCases(int $organizationId, int $actorId, array $exchangeRateIds): void
    {
        if (
            ! Schema::hasTable('orders') ||
            ! Schema::hasTable('reconciliations') ||
            ! Schema::hasTable('order_items') ||
            ! Schema::hasTable('order_status_logs')
        ) {
            return;
        }

        $warehouseIds = DB::table('warehouses')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($warehouseIds === []) {
            return;
        }

        $customerId = $this->resolveShowcaseCustomerId($organizationId);

        if (! $customerId) {
            return;
        }

        $productIds = $this->resolveShowcaseProductIds($organizationId, 3);

        if ($productIds === []) {
            return;
        }

        $cases = [
            [
                'code' => sprintf('ORD-FOLLOW-DEL-30H-O%d', $organizationId),
                'ghn_order_code' => sprintf('GHN-FOLLOW-DEL-30H-O%d', $organizationId),
                'warehouse_id' => (int) $warehouseIds[0],
                'status' => OrderStatus::SHIPPING->value,
                'ghn_status' => GhnOrderStatus::DELIVERING->value,
                'ghn_status_label' => GhnOrderStatus::DELIVERING->label(),
                'posted_at' => now()->subHours(30),
                'confirmed_log_at' => now()->subHours(32),
                'reconciliation_status' => ReconciliationStatus::PENDING->value,
                'ghn_employee_note' => 'Theo doi don demo: qua 24h chua hoan tat giao hang',
                'reconciliation_note' => 'Demo follow order delivery 30h',
                'items' => [
                    ['product_id' => $productIds[0], 'quantity' => 2, 'price' => 125000],
                ],
            ],
            [
                'code' => sprintf('ORD-FOLLOW-DEL-5D-O%d', $organizationId),
                'ghn_order_code' => sprintf('GHN-FOLLOW-DEL-5D-O%d', $organizationId),
                'warehouse_id' => (int) $warehouseIds[min(1, count($warehouseIds) - 1)],
                'status' => OrderStatus::SHIPPING->value,
                'ghn_status' => GhnOrderStatus::SORTING->value,
                'ghn_status_label' => GhnOrderStatus::SORTING->label(),
                'posted_at' => now()->subHours(122),
                'confirmed_log_at' => now()->subHours(126),
                'reconciliation_status' => ReconciliationStatus::CONFIRMED->value,
                'ghn_employee_note' => 'Theo doi don demo: qua 5 ngay chua hoan tat giao hang',
                'reconciliation_note' => 'Demo follow order delivery 5d',
                'items' => [
                    ['product_id' => $productIds[1] ?? $productIds[0], 'quantity' => 5, 'price' => 88000],
                ],
            ],
            [
                'code' => sprintf('ORD-FOLLOW-PICK-40H-O%d', $organizationId),
                'ghn_order_code' => sprintf('GHN-FOLLOW-PICK-40H-O%d', $organizationId),
                'warehouse_id' => (int) $warehouseIds[min(2, count($warehouseIds) - 1)],
                'status' => OrderStatus::SHIPPING->value,
                'ghn_status' => GhnOrderStatus::READY_TO_PICK->value,
                'ghn_status_label' => GhnOrderStatus::READY_TO_PICK->label(),
                'posted_at' => now()->subHours(40),
                'confirmed_log_at' => now()->subHours(42),
                'reconciliation_status' => ReconciliationStatus::PENDING->value,
                'ghn_employee_note' => 'Theo doi don demo: qua 36h chua lay hang',
                'reconciliation_note' => 'Demo follow order pickup 40h',
                'items' => [
                    ['product_id' => $productIds[2] ?? $productIds[0], 'quantity' => 3, 'price' => 99000],
                ],
            ],
            [
                'code' => sprintf('ORD-FOLLOW-PICK-80H-O%d', $organizationId),
                'ghn_order_code' => sprintf('GHN-FOLLOW-PICK-80H-O%d', $organizationId),
                'warehouse_id' => (int) $warehouseIds[0],
                'status' => OrderStatus::SHIPPING->value,
                'ghn_status' => GhnOrderStatus::PICKING->value,
                'ghn_status_label' => GhnOrderStatus::PICKING->label(),
                'posted_at' => now()->subHours(80),
                'confirmed_log_at' => now()->subHours(82),
                'reconciliation_status' => ReconciliationStatus::CONFIRMED->value,
                'ghn_employee_note' => 'Theo doi don demo: qua 72h chua lay hang',
                'reconciliation_note' => 'Demo follow order pickup 80h',
                'items' => [
                    ['product_id' => $productIds[0], 'quantity' => 4, 'price' => 110000],
                ],
            ],
            [
                'code' => sprintf('ORD-FOLLOW-POST-30H-O%d', $organizationId),
                'ghn_order_code' => sprintf('GHN-FOLLOW-POST-30H-O%d', $organizationId),
                'warehouse_id' => (int) $warehouseIds[min(1, count($warehouseIds) - 1)],
                'status' => OrderStatus::CONFIRMED->value,
                'ghn_status' => null,
                'ghn_status_label' => 'Chua dang don',
                'posted_at' => null,
                'confirmed_log_at' => now()->subHours(30),
                'reconciliation_status' => ReconciliationStatus::PENDING->value,
                'ghn_employee_note' => 'Theo doi don demo: qua 24h chua dang don',
                'reconciliation_note' => 'Demo follow order posting 30h',
                'items' => [
                    ['product_id' => $productIds[1] ?? $productIds[0], 'quantity' => 2, 'price' => 135000],
                ],
            ],
            [
                'code' => sprintf('ORD-FOLLOW-POST-80H-O%d', $organizationId),
                'ghn_order_code' => sprintf('GHN-FOLLOW-POST-80H-O%d', $organizationId),
                'warehouse_id' => (int) $warehouseIds[min(2, count($warehouseIds) - 1)],
                'status' => OrderStatus::CONFIRMED->value,
                'ghn_status' => null,
                'ghn_status_label' => 'Chua dang don',
                'posted_at' => null,
                'confirmed_log_at' => now()->subHours(80),
                'reconciliation_status' => ReconciliationStatus::CONFIRMED->value,
                'ghn_employee_note' => 'Theo doi don demo: qua 72h chua dang don',
                'reconciliation_note' => 'Demo follow order posting 80h',
                'items' => [
                    ['product_id' => $productIds[2] ?? $productIds[0], 'quantity' => 6, 'price' => 72000],
                ],
            ],
        ];

        foreach ($cases as $index => $case) {
            $createdAt = ($case['confirmed_log_at'] ?? now())->copy()->subHours(6)->setSecond(0);
            $orderId = $this->upsertFollowOrderShowcaseOrder(
                organizationId: $organizationId,
                customerId: (int) $customerId,
                actorId: $actorId,
                code: $case['code'],
                warehouseId: (int) $case['warehouse_id'],
                orderStatus: (int) $case['status'],
                ghnOrderCode: $case['ghn_order_code'],
                ghnStatus: $case['ghn_status'],
                createdAt: $createdAt,
                postedAt: $case['posted_at'],
                items: $case['items']
            );

            $this->syncFollowOrderStatusLogs(
                orderId: $orderId,
                actorId: $actorId,
                confirmedAt: $case['confirmed_log_at'],
                postedAt: $case['posted_at']
            );

            $this->upsertFollowOrderReconciliation(
                organizationId: $organizationId,
                actorId: $actorId,
                exchangeRateIds: $exchangeRateIds,
                orderId: $orderId,
                index: $index + 1,
                ghnOrderCode: $case['ghn_order_code'],
                ghnStatusLabel: (string) $case['ghn_status_label'],
                postedAt: $case['posted_at'],
                reconciliationStatus: (int) $case['reconciliation_status'],
                ghnEmployeeNote: (string) $case['ghn_employee_note'],
                note: (string) $case['reconciliation_note']
            );
        }
    }

    private function upsertFollowOrderShowcaseOrder(
        int $organizationId,
        int $customerId,
        int $actorId,
        string $code,
        int $warehouseId,
        int $orderStatus,
        string $ghnOrderCode,
        ?string $ghnStatus,
        Carbon $createdAt,
        ?Carbon $postedAt,
        array $items
    ): int {
        $existingOrderId = DB::table('orders')
            ->where('code', $code)
            ->value('id');

        $shippingFee = 30000;
        $totalAmount = collect($items)->sum(fn (array $item): int => ((int) $item['quantity']) * ((int) $item['price']));
        $payload = [
            'organization_id' => $organizationId,
            'customer_id' => $customerId,
            'warehouse_id' => $warehouseId,
            'status' => $orderStatus,
            'total_amount' => $totalAmount,
            'discount' => 0,
            'shipping_fee' => $shippingFee,
            'deposit' => 0,
            'shipping_method' => ProviderShipping::GHN->value,
            'provider_shipping' => ProviderShipping::GHN->value,
            'ghn_order_code' => $ghnOrderCode,
            'ghn_status' => $ghnStatus,
            'ghn_posted_at' => $postedAt,
            'collect_amount' => $totalAmount + $shippingFee,
            'care_updated_at' => $createdAt->copy()->addHour(),
            'care_by_id' => $actorId,
            'is_printed' => false,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'updated_at' => $postedAt?->copy() ?? $createdAt->copy()->addHours(2),
            'deleted_at' => null,
        ];

        if ($existingOrderId) {
            DB::table('orders')
                ->where('id', $existingOrderId)
                ->update($payload);

            $orderId = (int) $existingOrderId;
        } else {
            $orderId = (int) DB::table('orders')->insertGetId([
                ...$payload,
                'code' => $code,
                'created_at' => $createdAt,
            ]);
        }

        DB::table('order_items')->where('order_id', $orderId)->delete();

        foreach ($items as $item) {
            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'price' => (int) $item['price'],
                'total' => (int) $item['quantity'] * (int) $item['price'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $orderId;
    }

    private function syncFollowOrderStatusLogs(int $orderId, int $actorId, ?Carbon $confirmedAt, ?Carbon $postedAt): void
    {
        DB::table('order_status_logs')->where('order_id', $orderId)->delete();

        if ($confirmedAt) {
            DB::table('order_status_logs')->insert([
                'order_id' => $orderId,
                'user_id' => $actorId,
                'from_status' => OrderStatus::PENDING->value,
                'to_status' => OrderStatus::CONFIRMED->value,
                'note' => 'seed_follow_order_confirmed',
                'created_at' => $confirmedAt,
                'updated_at' => $confirmedAt,
            ]);
        }

        if ($postedAt) {
            DB::table('order_status_logs')->insert([
                'order_id' => $orderId,
                'user_id' => $actorId,
                'from_status' => OrderStatus::CONFIRMED->value,
                'to_status' => OrderStatus::SHIPPING->value,
                'note' => 'seed_follow_order_posted',
                'created_at' => $postedAt,
                'updated_at' => $postedAt,
            ]);
        }
    }

    private function upsertFollowOrderReconciliation(
        int $organizationId,
        int $actorId,
        array $exchangeRateIds,
        int $orderId,
        int $index,
        string $ghnOrderCode,
        string $ghnStatusLabel,
        ?Carbon $postedAt,
        int $reconciliationStatus,
        string $ghnEmployeeNote,
        string $note
    ): void {
        DB::table('reconciliations')
            ->where('organization_id', $organizationId)
            ->where('ghn_order_code', $ghnOrderCode)
            ->delete();

        $reconciliationDate = now()->subDays($index - 1)->toDateString();
        $confirmedAt = in_array($reconciliationStatus, [
            ReconciliationStatus::CONFIRMED->value,
            ReconciliationStatus::PAID->value,
        ], true)
            ? now()->subDays($index - 1)->setTime(18, 0)
            : null;

        DB::table('reconciliations')->insert([
            'organization_id' => $organizationId,
            'reconciliation_date' => $reconciliationDate,
            'order_id' => $orderId,
            'ghn_order_code' => $ghnOrderCode,
            'ghn_to_name' => 'Khach test theo doi don #' . $index,
            'ghn_to_phone' => '0918' . str_pad((string) (300000 + $index), 6, '0', STR_PAD_LEFT),
            'ghn_to_address' => 'Dia chi test theo doi don #' . $index,
            'ghn_status_label' => $ghnStatusLabel,
            'ghn_created_at' => $postedAt ?? now()->subHours(6 + $index),
            'ghn_updated_at' => $postedAt?->copy()->addHour() ?? now()->subHours($index),
            'ghn_items' => $this->buildReconciliationGhnItems($orderId),
            'ghn_payment_type_id' => 2,
            'ghn_weight' => 500 + ($index * 50),
            'ghn_content' => 'Theo doi don demo',
            'ghn_required_note' => 'CHOXEMHANGKHONGTHU',
            'ghn_employee_note' => $ghnEmployeeNote,
            'ghn_cod_failed_amount' => 0,
            'cod_amount' => 450000 + ($index * 50000),
            'shipping_fee' => 25000 + ($index * 1000),
            'storage_fee' => 0,
            'total_fee' => 25000 + ($index * 1000),
            'exchange_rate_id' => $exchangeRateIds[0] ?? null,
            'converted_amount' => 0,
            'status' => $reconciliationStatus,
            'note' => $note,
            'is_internal_reconciled' => false,
            'created_by' => $actorId,
            'confirmed_by' => $confirmedAt ? $actorId : null,
            'confirmed_at' => $confirmedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolveShowcaseProductIds(int $organizationId, int $minimum = 1): array
    {
        $productIds = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit($minimum)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($productIds) >= $minimum) {
            return $productIds;
        }

        for ($index = count($productIds) + 1; $index <= $minimum; $index++) {
            $productIds[] = (int) DB::table('products')->insertGetId([
                'organization_id' => $organizationId,
                'name' => 'San pham theo doi don demo ' . $index,
                'sku' => sprintf('FOLLOW-DEMO-%d-%d', $organizationId, $index),
                'unit' => 'Hop',
                'weight' => 200,
                'cost_price' => 50000,
                'sale_price' => 99000 + ($index * 1000),
                'quantity' => 100,
                'vat_rate' => 0,
                'type_vat' => 1,
                'is_business_product' => false,
                'has_attributes' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $productIds;
    }

    private function resolveReconciliationOrderIds(int $organizationId, int $actorId, array $preferredOrderIds = []): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        $preferredOrderIds = collect($preferredOrderIds)
            ->filter()
            ->map(fn ($orderId) => (int) $orderId)
            ->unique()
            ->values()
            ->all();

        $ordersWithWarehouse = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->whereNotNull('warehouse_id')
            ->orderByDesc('id')
            ->limit(40)
            ->pluck('id')
            ->all();

        if (! empty($ordersWithWarehouse) || ! empty($preferredOrderIds)) {
            return collect($preferredOrderIds)
                ->merge($ordersWithWarehouse)
                ->filter()
                ->map(fn ($orderId) => (int) $orderId)
                ->unique()
                ->take(40)
                ->values()
                ->all();
        }

        $demoOrderIds = $this->createShowcaseOrdersWithWarehouse($organizationId, $actorId);

        if (! empty($demoOrderIds)) {
            return $demoOrderIds;
        }

        return DB::table('orders')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(40)
            ->pluck('id')
            ->all();
    }

    private function createShowcaseOrdersWithWarehouse(int $organizationId, int $actorId): array
    {
        if (! Schema::hasTable('warehouses') || ! Schema::hasTable('orders')) {
            return [];
        }

        $warehouseIds = DB::table('warehouses')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (empty($warehouseIds)) {
            return [];
        }

        $customerId = $this->resolveShowcaseCustomerId($organizationId);

        if (! $customerId) {
            return [];
        }

        $createdOrderIds = [];
        $ordersToCreate = min(max(count($warehouseIds), 3), 6);

        for ($i = 1; $i <= $ordersToCreate; $i++) {
            $warehouseId = $warehouseIds[($i - 1) % count($warehouseIds)];
            $createdAt = now()->subDays(20 - $i)->setTime(15, 32);

            $createdOrderIds[] = DB::table('orders')->insertGetId([
                'organization_id' => $organizationId,
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'code' => 'ORD-ACC-' . Str::upper(Str::random(8)),
                'status' => OrderStatus::COMPLETED->value,
                'total_amount' => rand(450000, 2500000),
                'discount' => 0,
                'shipping_fee' => rand(18000, 70000),
                'shipping_method' => ProviderShipping::GHN->value,
                'provider_shipping' => ProviderShipping::GHN->value,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addMinutes(rand(10, 90)),
            ]);
        }

        return $createdOrderIds;
    }

    private function resolveShowcaseCustomerId(int $organizationId): ?int
    {
        if (! Schema::hasTable('customers')) {
            return null;
        }

        $existingCustomerId = DB::table('customers')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        if ($existingCustomerId) {
            return (int) $existingCustomerId;
        }

        return (int) DB::table('customers')->insertGetId([
            'organization_id' => $organizationId,
            'username' => 'Khach demo doi soat kho',
            'phone' => '0909000000',
            'customer_type' => CustomerType::NEW->value,
            'interaction_status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

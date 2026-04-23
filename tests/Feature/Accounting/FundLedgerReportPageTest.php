<?php

use App\Common\Constants\Organization\FundTransactionStatus;
use App\Common\Constants\Organization\FundTransactionType;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\Pages\FundLedgerReportPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAccountingOrganization(): int
{
    return DB::table('organizations')->insertGetId([
        'name' => 'Test Accounting Org',
        'code' => 'TEST-ACC-ORG',
        'product_field' => 1,
        'disable' => false,
        'maximum_employees' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createAccountingAdmin(int $organizationId): User
{
    return User::factory()->create([
        'organization_id' => $organizationId,
        'role' => UserRole::ADMIN->value,
        'position' => 2,
        'name' => 'Accounting Admin',
        'username' => 'accounting_admin',
        'email' => 'accounting-admin@example.com',
    ]);
}

it('shows required validation errors when from and to dates are empty', function () {
    $organizationId = createAccountingOrganization();
    $admin = createAccountingAdmin($organizationId);

    $this->actingAs($admin);

    Livewire::test(FundLedgerReportPage::class)
        ->set('data.from_date', null)
        ->set('data.to_date', null)
        ->call('generateReport')
        ->assertHasErrors([
            'data.from_date' => ['required'],
            'data.to_date' => ['required'],
        ]);
});

it('shows a validation error when to date is before from date', function () {
    $organizationId = createAccountingOrganization();
    $admin = createAccountingAdmin($organizationId);

    $this->actingAs($admin);

    Livewire::test(FundLedgerReportPage::class)
        ->set('data.from_date', '2026-04-20')
        ->set('data.to_date', '2026-04-10')
        ->call('generateReport')
        ->assertHasErrors(['data.to_date']);
});

it('applies fund and counterparty filters together and excludes pending transactions', function () {
    $organizationId = createAccountingOrganization();
    $admin = createAccountingAdmin($organizationId);

    $fundId = DB::table('funds')->insertGetId([
        'organization_id' => $organizationId,
        'balance' => 1000,
        'currency' => 'VND',
        'fund_type' => 'cash',
        'is_locked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherFundId = DB::table('funds')->insertGetId([
        'organization_id' => $organizationId,
        'balance' => 2000,
        'currency' => 'VND',
        'fund_type' => 'bank',
        'is_locked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('fund_transactions')->insert([
        [
            'fund_id' => $fundId,
            'transaction_date' => '2026-04-10',
            'type' => FundTransactionType::DEPOSIT->value,
            'transaction_code' => 'FT-MATCH-001',
            'balance_after' => 1500,
            'amount' => 500,
            'counterparty_name' => 'Khach Hang A',
            'currency' => 'VND',
            'description' => 'Matched row',
            'purpose' => 'Thu tien',
            'status' => FundTransactionStatus::COMPLETED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'fund_id' => $fundId,
            'transaction_date' => '2026-04-12',
            'type' => FundTransactionType::DEPOSIT->value,
            'transaction_code' => 'FT-PENDING-001',
            'balance_after' => 2500,
            'amount' => 1000,
            'counterparty_name' => 'Khach Hang A',
            'currency' => 'VND',
            'description' => 'Pending row',
            'purpose' => 'Thu tien',
            'status' => FundTransactionStatus::PENDING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'fund_id' => $otherFundId,
            'transaction_date' => '2026-04-15',
            'type' => FundTransactionType::DEPOSIT->value,
            'transaction_code' => 'FT-OTHER-FUND-001',
            'balance_after' => 3000,
            'amount' => 1000,
            'counterparty_name' => 'Khach Hang A',
            'currency' => 'VND',
            'description' => 'Other fund row',
            'purpose' => 'Thu tien',
            'status' => FundTransactionStatus::COMPLETED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'fund_id' => $fundId,
            'transaction_date' => '2026-04-18',
            'type' => FundTransactionType::DEPOSIT->value,
            'transaction_code' => 'FT-OTHER-NAME-001',
            'balance_after' => 3500,
            'amount' => 2000,
            'counterparty_name' => 'Nha Cung Cap B',
            'currency' => 'VND',
            'description' => 'Other counterparty row',
            'purpose' => 'Thu tien',
            'status' => FundTransactionStatus::COMPLETED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'fund_id' => $fundId,
            'transaction_date' => '2026-03-28',
            'type' => FundTransactionType::DEPOSIT->value,
            'transaction_code' => 'FT-OUTSIDE-RANGE-001',
            'balance_after' => 1200,
            'amount' => 200,
            'counterparty_name' => 'Khach Hang A',
            'currency' => 'VND',
            'description' => 'Outside range row',
            'purpose' => 'Thu tien',
            'status' => FundTransactionStatus::COMPLETED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($admin);

    Livewire::test(FundLedgerReportPage::class)
        ->set('data.from_date', '2026-04-01')
        ->set('data.to_date', '2026-04-30')
        ->set('data.fund_id', $fundId)
        ->set('data.counterparty_name', 'khach')
        ->call('generateReport')
        ->assertSet('rows', function (array $rows) {
            expect($rows)->toHaveCount(1);
            expect($rows[0]['transaction_code'])->toBe('FT-MATCH-001');
            expect($rows[0]['counterparty_name'])->toBe('Khach Hang A');

            return true;
        })
        ->assertSet('summary.total_in', 500.0)
        ->assertSet('summary.total_out', 0.0);
});

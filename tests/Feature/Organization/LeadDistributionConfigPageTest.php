<?php

namespace Tests\Feature\Organization;

use App\Common\Constants\Customer\DistributionMethod;
use App\Common\Constants\Team\TeamType;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Organization\Pages\LeadDistributionConfig as LeadDistributionConfigPage;
use App\Models\User;
use App\Support\LeadDistributionConfigRuleMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class LeadDistributionConfigPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_fixed_rule_matrix_without_bill_of_lading(): void
    {
        $organizationId = $this->createOrganization();
        $admin = $this->createUser($organizationId, 'lead-config-admin', UserRole::ADMIN);

        $this->actingAs($admin);

        Livewire::test(LeadDistributionConfigPage::class)
            ->assertSet('data.rules', function (array $rules): bool {
                $staffTypes = collect($rules)
                    ->pluck('staff_type')
                    ->map(fn ($value): int => (int) $value)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $this->assertCount(LeadDistributionConfigRuleMatrix::expectedRuleCount(), $rules);
                $this->assertSame(
                    [TeamType::SALE->value, TeamType::CSKH->value],
                    $staffTypes,
                );
                $this->assertNotContains(TeamType::BILL_OF_LADING->value, $staffTypes);

                return true;
            });
    }

    public function test_page_blocks_duplicate_staff_in_same_pool(): void
    {
        $organizationId = $this->createOrganization();
        $admin = $this->createUser($organizationId, 'lead-config-admin-dup', UserRole::ADMIN);
        [$saleTeamId, $cskhTeamId, $saleStaff, $cskhStaff] = $this->createStaffPools($organizationId);

        $this->actingAs($admin);

        Livewire::test(LeadDistributionConfigPage::class)
            ->set('data.staffSale', [
                [
                    'team_id' => $saleTeamId,
                    'staff_id' => $saleStaff->id,
                    'weight' => 10,
                ],
                [
                    'team_id' => $saleTeamId,
                    'staff_id' => $saleStaff->id,
                    'weight' => 20,
                ],
            ])
            ->set('data.staffCSKH', [
                [
                    'team_id' => $cskhTeamId,
                    'staff_id' => $cskhStaff->id,
                    'weight' => 5,
                ],
            ])
            ->call('save')
            ->assertHasErrors([
                'data.staffSale.0.staff_id',
                'data.staffSale.1.staff_id',
            ]);
    }

    public function test_page_saves_distribution_method_for_fixed_rule_matrix(): void
    {
        $organizationId = $this->createOrganization();
        $admin = $this->createUser($organizationId, 'lead-config-admin-save', UserRole::ADMIN);
        [$saleTeamId, $cskhTeamId, $saleStaff, $cskhStaff] = $this->createStaffPools($organizationId);

        $rules = LeadDistributionConfigRuleMatrix::defaultRules();
        $rules[0]['distribution_method'] = DistributionMethod::BY_DEFINITION->value;

        $this->actingAs($admin);

        Livewire::test(LeadDistributionConfigPage::class)
            ->set('data.name', 'Cau hinh chia so')
            ->set('data.rules', $rules)
            ->set('data.staffSale', [
                [
                    'team_id' => $saleTeamId,
                    'staff_id' => $saleStaff->id,
                    'weight' => 10,
                ],
            ])
            ->set('data.staffCSKH', [
                [
                    'team_id' => $cskhTeamId,
                    'staff_id' => $cskhStaff->id,
                    'weight' => 15,
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $configId = DB::table('lead_distribution_configs')
            ->where('organization_id', $organizationId)
            ->value('id');

        $this->assertNotNull($configId);

        $this->assertDatabaseHas('lead_distribution_rules', [
            'config_id' => $configId,
            'customer_type' => $rules[0]['customer_type'],
            'staff_type' => $rules[0]['staff_type'],
            'distribution_method' => DistributionMethod::BY_DEFINITION->value,
            'deleted_at' => null,
        ]);

        $this->assertSame(
            LeadDistributionConfigRuleMatrix::expectedRuleCount(),
            DB::table('lead_distribution_rules')
                ->where('config_id', $configId)
                ->whereNull('deleted_at')
                ->count(),
        );
    }

    private function createOrganization(): int
    {
        return DB::table('organizations')->insertGetId([
            'name' => 'Test Organization',
            'code' => 'ORG-' . fake()->unique()->numerify('###'),
            'product_field' => 1,
            'disable' => false,
            'maximum_employees' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(int $organizationId, string $username, UserRole $role): User
    {
        return User::factory()->create([
            'organization_id' => $organizationId,
            'role' => $role->value,
            'position' => 2,
            'username' => $username,
            'email' => $username . '@example.com',
            'name' => ucfirst(str_replace('-', ' ', $username)),
            'disable' => false,
        ]);
    }

    /**
     * @return array{0:int,1:int,2:User,3:User}
     */
    private function createStaffPools(int $organizationId): array
    {
        $saleTeamId = $this->createTeam($organizationId, TeamType::SALE, 'SALE');
        $cskhTeamId = $this->createTeam($organizationId, TeamType::CSKH, 'CSKH');

        $saleStaff = $this->createUser($organizationId, 'sale-staff-' . fake()->unique()->numerify('###'), UserRole::SALE);
        $cskhStaff = $this->createUser($organizationId, 'cskh-staff-' . fake()->unique()->numerify('###'), UserRole::SALE);

        $this->attachUserToTeam($saleStaff->id, $saleTeamId);
        $this->attachUserToTeam($cskhStaff->id, $cskhTeamId);

        return [$saleTeamId, $cskhTeamId, $saleStaff, $cskhStaff];
    }

    private function createTeam(int $organizationId, TeamType $teamType, string $prefix): int
    {
        return DB::table('teams')->insertGetId([
            'name' => $prefix . ' Team',
            'organization_id' => $organizationId,
            'code' => $prefix . '-' . fake()->unique()->numerify('###'),
            'type' => $teamType->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachUserToTeam(int $userId, int $teamId): void
    {
        DB::table('user_team')->insert([
            'user_id' => $userId,
            'team_id' => $teamId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

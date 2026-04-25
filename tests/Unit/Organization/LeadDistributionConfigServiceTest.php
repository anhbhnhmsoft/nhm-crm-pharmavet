<?php

namespace Tests\Unit\Organization;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\DistributionMethod;
use App\Common\Constants\Team\TeamType;
use App\Common\Constants\User\UserRole;
use App\Models\LeadDistributionConfig;
use App\Models\User;
use App\Services\LeadDistributionConfigService;
use App\Support\LeadDistributionConfigRuleMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeadDistributionConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_rejects_duplicate_rule_keys(): void
    {
        $organizationId = $this->createOrganization();
        $admin = $this->createUser($organizationId, 'service-admin-dup', UserRole::ADMIN);
        [$saleTeamId, $cskhTeamId, $saleStaff, $cskhStaff] = $this->createStaffPools($organizationId);

        $this->actingAs($admin);

        /** @var LeadDistributionConfigService $service */
        $service = app(LeadDistributionConfigService::class);
        $config = $service->getLeadDistributionConfig($organizationId)->getData();

        $rules = LeadDistributionConfigRuleMatrix::defaultRules();
        $rules[1]['customer_type'] = $rules[0]['customer_type'];
        $rules[1]['staff_type'] = $rules[0]['staff_type'];

        try {
            $service->saveLeadDistributionConfig($config, [
                'name' => 'Test config',
                'product_id' => null,
                'rules' => $rules,
                'staffSale' => [
                    [
                        'team_id' => $saleTeamId,
                        'staff_id' => $saleStaff->id,
                        'weight' => 10,
                    ],
                ],
                'staffCSKH' => [
                    [
                        'team_id' => $cskhTeamId,
                        'staff_id' => $cskhStaff->id,
                        'weight' => 10,
                    ],
                ],
            ], $organizationId);

            $this->fail('Expected duplicate rule keys to throw a validation exception.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey('data.rules.0.distribution_method', $errors);
            $this->assertArrayHasKey('data.rules.1.distribution_method', $errors);
        }
    }

    public function test_service_removes_invalid_legacy_rules_when_saving_fixed_matrix(): void
    {
        $organizationId = $this->createOrganization();
        $admin = $this->createUser($organizationId, 'service-admin-cleanup', UserRole::ADMIN);
        [$saleTeamId, $cskhTeamId, $saleStaff, $cskhStaff] = $this->createStaffPools($organizationId);

        $this->actingAs($admin);

        $config = LeadDistributionConfig::query()->create([
            'organization_id' => $organizationId,
            'name' => 'Legacy config',
            'product_id' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $invalidRuleId = DB::table('lead_distribution_rules')->insertGetId([
            'config_id' => $config->id,
            'customer_type' => CustomerType::NEW->value,
            'staff_type' => TeamType::BILL_OF_LADING->value,
            'distribution_method' => DistributionMethod::MOST_RECENT_RECIPIENT->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rules = LeadDistributionConfigRuleMatrix::defaultRules();
        $rules[0]['distribution_method'] = DistributionMethod::BY_DEFINITION->value;

        /** @var LeadDistributionConfigService $service */
        $service = app(LeadDistributionConfigService::class);
        $result = $service->saveLeadDistributionConfig($config, [
            'name' => 'Legacy config updated',
            'product_id' => null,
            'rules' => $rules,
            'staffSale' => [
                [
                    'team_id' => $saleTeamId,
                    'staff_id' => $saleStaff->id,
                    'weight' => 20,
                ],
            ],
            'staffCSKH' => [
                [
                    'team_id' => $cskhTeamId,
                    'staff_id' => $cskhStaff->id,
                    'weight' => 25,
                ],
            ],
        ], $organizationId);

        $this->assertTrue($result->isSuccess());

        $this->assertNotNull(
            DB::table('lead_distribution_rules')
                ->where('id', $invalidRuleId)
                ->value('deleted_at')
        );

        $this->assertSame(
            LeadDistributionConfigRuleMatrix::expectedRuleCount(),
            DB::table('lead_distribution_rules')
                ->where('config_id', $config->id)
                ->whereNull('deleted_at')
                ->count(),
        );

        $this->assertDatabaseHas('lead_distribution_rules', [
            'config_id' => $config->id,
            'customer_type' => $rules[0]['customer_type'],
            'staff_type' => $rules[0]['staff_type'],
            'distribution_method' => DistributionMethod::BY_DEFINITION->value,
            'deleted_at' => null,
        ]);
    }

    private function createOrganization(): int
    {
        return DB::table('organizations')->insertGetId([
            'name' => 'Unit Test Organization',
            'code' => 'UT-ORG-' . fake()->unique()->numerify('###'),
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
        $saleTeamId = $this->createTeam($organizationId, TeamType::SALE, 'UT-SALE');
        $cskhTeamId = $this->createTeam($organizationId, TeamType::CSKH, 'UT-CSKH');

        $saleStaff = $this->createUser($organizationId, 'unit-sale-' . fake()->unique()->numerify('###'), UserRole::SALE);
        $cskhStaff = $this->createUser($organizationId, 'unit-cskh-' . fake()->unique()->numerify('###'), UserRole::SALE);

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

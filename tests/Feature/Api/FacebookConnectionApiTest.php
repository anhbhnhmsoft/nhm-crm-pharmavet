<?php

namespace Tests\Feature\Api;

use App\Common\Constants\User\UserRole;
use App\Models\Integration;
use App\Models\User;
use App\Services\Integrations\MetaBusinessService;
use Illuminate\Auth\Middleware\Authenticate;
use Tests\TestCase;

class FacebookConnectionApiTest extends TestCase
{
    public function test_marketing_user_can_connect_via_api(): void
    {
        $this->withoutMiddleware(Authenticate::class);

        $user = new User([
            'id' => 10,
            'organization_id' => 5,
            'role' => UserRole::MARKETING->value,
            'name' => 'Marketing User',
            'username' => 'marketing.user',
            'password' => 'secret',
        ]);
        $user->id = 10;

        $integration = new Integration([
            'id' => 11,
            'organization_id' => 5,
            'name' => 'Facebook Lead Ads',
        ]);
        $integration->id = 11;

        $this->actingAs($user);

        $this->mock(MetaBusinessService::class, function ($mock) use ($user, $integration) {
            $mock->shouldReceive('connectWithUserAccessToken')
                ->once()
                ->withArgs(fn ($actor, $token) => $actor->id === $user->id && $token === 'user-access-token')
                ->andReturn(\App\Core\ServiceReturn::success([
                    'integration_id' => $integration->id,
                    'count' => 1,
                    'pages' => [],
                ], 'pending'));
        });

        $this->postJson('/api/v1/facebook/connect', [
            'userAccessToken' => 'user-access-token',
        ])
            ->assertOk()
            ->assertJsonPath('data.integration_id', 11)
            ->assertJsonPath('data.count', 1);
    }

    public function test_marketing_user_cannot_access_admin_approve_endpoint(): void
    {
        $this->withoutMiddleware(Authenticate::class);

        $user = new User([
            'id' => 12,
            'organization_id' => 5,
            'role' => UserRole::MARKETING->value,
            'name' => 'Marketing User',
            'username' => 'marketing.approve',
            'password' => 'secret',
        ]);
        $user->id = 12;

        $this->actingAs($user);

        $this->postJson('/api/v1/admin/facebook/approve', [
            'integration_id' => 999,
        ])->assertForbidden();
    }
}

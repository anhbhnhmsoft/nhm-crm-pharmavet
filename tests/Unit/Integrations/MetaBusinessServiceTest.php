<?php

namespace Tests\Unit\Integrations;

use App\Repositories\CustomerRepository;
use App\Repositories\FacebookLeadRepository;
use App\Repositories\IntegrationEntityRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\IntegrationTokenRepository;
use App\Repositories\LeadDistributionConfigRepository;
use App\Services\Integrations\FacebookLeadMapper;
use App\Services\Integrations\MetaBusinessService;
use App\Services\LeadDistributionService;
use Tests\TestCase;

class MetaBusinessServiceTest extends TestCase
{
    public function test_it_resolves_mapping_for_both_mapping_conventions(): void
    {
        $service = $this->makeService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveMappedLeadValue');
        $method->setAccessible(true);

        $fields = [
            'full_name' => 'Nguyen Van A',
            'phone_number' => '0909000001',
            'email_address' => 'a@example.com',
        ];

        $crmToSource = [
            'name' => 'full_name',
            'phone' => 'phone_number',
            'email' => 'email_address',
        ];
        $sourceToCrm = [
            'full_name' => 'name',
            'phone_number' => 'phone',
            'email_address' => 'email',
        ];

        $nameByCrmToSource = $method->invoke($service, $fields, $crmToSource, 'name', ['name']);
        $nameBySourceToCrm = $method->invoke($service, $fields, $sourceToCrm, 'name', ['name']);

        $this->assertSame('Nguyen Van A', $nameByCrmToSource);
        $this->assertSame('Nguyen Van A', $nameBySourceToCrm);
    }

    public function test_it_requires_manage_and_advertise_or_create_content_for_leadgen_pages(): void
    {
        $service = $this->makeService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('hasLeadgenPermission');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, ['MANAGE', 'ADVERTISE']));
        $this->assertTrue($method->invoke($service, ['MANAGE', 'CREATE_CONTENT']));
        $this->assertFalse($method->invoke($service, ['ADVERTISE']));
        $this->assertFalse($method->invoke($service, ['MANAGE']));
    }

    public function test_it_verifies_webhook_token_from_env_config(): void
    {
        config()->set('services.facebook.webhook_verify_token', 'verify-token-123');

        $service = $this->makeService();

        $this->assertTrue($service->verifyIntegrationByWebhookToken('verify-token-123')->isSuccess());
        $this->assertTrue($service->verifyIntegrationByWebhookToken('invalid-token')->isError());
    }

    protected function makeService(): MetaBusinessService
    {
        return new MetaBusinessService(
            $this->createMock(CustomerRepository::class),
            $this->createMock(IntegrationRepository::class),
            $this->createMock(IntegrationTokenRepository::class),
            $this->createMock(IntegrationEntityRepository::class),
            $this->createMock(LeadDistributionConfigRepository::class),
            $this->createMock(LeadDistributionService::class),
            $this->createMock(FacebookLeadRepository::class),
            new FacebookLeadMapper(),
        );
    }
}

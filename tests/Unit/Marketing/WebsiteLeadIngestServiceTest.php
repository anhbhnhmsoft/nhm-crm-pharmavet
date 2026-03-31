<?php

namespace Tests\Unit\Marketing;

use App\Repositories\CustomerRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\WebsiteLeadIngestLogRepository;
use App\Services\Marketing\WebsiteLeadIngestService;
use PHPUnit\Framework\TestCase;

class WebsiteLeadIngestServiceTest extends TestCase
{
    public function test_it_rejects_payload_with_unknown_root_keys(): void
    {
        $service = new WebsiteLeadIngestService(
            $this->createMock(IntegrationRepository::class),
            $this->createMock(CustomerRepository::class),
            $this->createMock(WebsiteLeadIngestLogRepository::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validatePayload');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'lead' => ['name' => 'A', 'phone' => '0123'],
            'unexpected' => 'x',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('payload', $result['errors']);
    }
}

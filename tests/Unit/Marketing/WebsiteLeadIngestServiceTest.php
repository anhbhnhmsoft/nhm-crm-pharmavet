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

    public function test_it_applies_mapping_with_source_to_crm_format(): void
    {
        $service = new WebsiteLeadIngestService(
            $this->createMock(IntegrationRepository::class),
            $this->createMock(CustomerRepository::class),
            $this->createMock(WebsiteLeadIngestLogRepository::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('applyIntegrationMapping');
        $method->setAccessible(true);

        $mapped = $method->invoke($service, [
            'full_name' => 'Nguyen Van A',
            'phone_number' => '0909 000 001',
        ], [
            'name' => 'Ping',
            'phone' => '',
            'email' => '',
            'address' => '',
            'note' => '',
            'source_detail' => '',
            'external_id' => '',
            'product_id' => 0,
        ], [
            'full_name' => 'name',
            'phone_number' => 'phone',
        ]);

        $this->assertSame('Nguyen Van A', $mapped['name']);
        $this->assertSame('0909 000 001', $mapped['phone']);
    }

    public function test_it_applies_mapping_with_legacy_crm_to_source_format(): void
    {
        $service = new WebsiteLeadIngestService(
            $this->createMock(IntegrationRepository::class),
            $this->createMock(CustomerRepository::class),
            $this->createMock(WebsiteLeadIngestLogRepository::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('applyIntegrationMapping');
        $method->setAccessible(true);

        $mapped = $method->invoke($service, [
            'full_name' => 'Le Thi B',
            'phone_number' => '0911 000 002',
        ], [
            'name' => 'Ping',
            'phone' => '',
            'email' => '',
            'address' => '',
            'note' => '',
            'source_detail' => '',
            'external_id' => '',
            'product_id' => 0,
        ], [
            'name' => 'full_name',
            'phone' => 'phone_number',
        ]);

        $this->assertSame('Le Thi B', $mapped['name']);
        $this->assertSame('0911 000 002', $mapped['phone']);
    }

    public function test_it_applies_default_values_when_mapped_field_is_empty(): void
    {
        $service = new WebsiteLeadIngestService(
            $this->createMock(IntegrationRepository::class),
            $this->createMock(CustomerRepository::class),
            $this->createMock(WebsiteLeadIngestLogRepository::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('applyDefaultValues');
        $method->setAccessible(true);

        $normalized = [
            'name' => 'A',
            'phone' => '0909',
            'email' => '',
            'address' => '',
            'note' => '',
            'source_detail' => '',
            'external_id' => '',
            'product_id' => 0,
        ];

        $result = $method->invoke($service, $normalized, [
            'email' => 'default@example.com',
            'source_detail' => 'landing_page',
        ]);

        $this->assertSame('default@example.com', $result['email']);
        $this->assertSame('landing_page', $result['source_detail']);
    }

    public function test_it_rejects_low_quality_phone_data(): void
    {
        $service = new WebsiteLeadIngestService(
            $this->createMock(IntegrationRepository::class),
            $this->createMock(CustomerRepository::class),
            $this->createMock(WebsiteLeadIngestLogRepository::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateNormalizedLead');
        $method->setAccessible(true);

        $errors = $method->invoke($service, [
            'phone' => '0000000000',
            'email' => '',
        ]);

        $this->assertArrayHasKey('lead.phone', $errors);
    }

    public function test_it_appends_inbound_tag_to_note(): void
    {
        $service = new WebsiteLeadIngestService(
            $this->createMock(IntegrationRepository::class),
            $this->createMock(CustomerRepository::class),
            $this->createMock(WebsiteLeadIngestLogRepository::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildCustomerNoteWithTag');
        $method->setAccessible(true);

        $note = $method->invoke($service, 'Khach de lai ghi chu', 'form_register');

        $this->assertStringContainsString('#form_register', $note);
    }
}

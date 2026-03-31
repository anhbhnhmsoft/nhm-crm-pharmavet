<?php

namespace Tests\Unit\Marketing;

use App\Repositories\FacebookEventLogRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\IntegrationTokenRepository;
use App\Services\Marketing\MarketingConversionService;
use PHPUnit\Framework\TestCase;

class MarketingConversionServiceTest extends TestCase
{
    public function test_it_hashes_user_data_with_sha256(): void
    {
        $service = new MarketingConversionService(
            $this->createMock(FacebookEventLogRepository::class),
            $this->createMock(IntegrationRepository::class),
            $this->createMock(IntegrationTokenRepository::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildHashedPayload');
        $method->setAccessible(true);

        $payload = [
            'event_name' => 'Lead',
            'user_data' => [
                'em' => 'TEST@MAIL.COM',
                'ph' => ' 0987 654 321 ',
            ],
        ];

        $hashed = $method->invoke($service, $payload);

        $this->assertSame(hash('sha256', 'test@mail.com'), $hashed['user_data']['em']);
        $this->assertSame(hash('sha256', '0987 654 321'), $hashed['user_data']['ph']);
    }
}

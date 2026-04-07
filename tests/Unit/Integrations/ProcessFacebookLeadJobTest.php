<?php

namespace Tests\Unit\Integrations;

use App\Core\ServiceReturn;
use App\Jobs\ProcessFacebookLeadJob;
use App\Services\Integrations\MetaBusinessService;
use PHPUnit\Framework\TestCase;

class ProcessFacebookLeadJobTest extends TestCase
{
    public function test_it_throws_exception_for_retryable_failures(): void
    {
        $service = $this->createMock(MetaBusinessService::class);
        $service->expects($this->once())
            ->method('processLead')
            ->with(99, '12345', 'lead_1')
            ->willReturn(ServiceReturn::error('Temporary error', data: ['retryable' => true]));

        $job = new ProcessFacebookLeadJob(99, 'lead_1', '12345');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Temporary error');

        $job->handle($service);
    }

    public function test_it_stops_retrying_for_non_retryable_failures(): void
    {
        $service = $this->createMock(MetaBusinessService::class);
        $service->expects($this->once())
            ->method('processLead')
            ->with(99, '12345', 'lead_1')
            ->willReturn(ServiceReturn::error('Blacklisted', data: ['retryable' => false]));

        $job = new ProcessFacebookLeadJob(99, 'lead_1', '12345');

        $job->handle($service);

        $this->assertTrue(true);
    }
}

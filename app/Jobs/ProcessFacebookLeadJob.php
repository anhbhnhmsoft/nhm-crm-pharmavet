<?php
namespace App\Jobs;


use App\Services\Integrations\MetaBusinessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFacebookLeadJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $integrationId;
    public string $leadId;
    public string $pageId;

    public int $tries = 5;
    public array $backoff = [60, 120, 300, 600, 900];

    public function __construct(int $integrationId, string $leadId, string $pageId)
    {
        $this->integrationId = $integrationId;
        $this->leadId = $leadId;
        $this->pageId = $pageId;
    }

    public function handle(MetaBusinessService $service)
    {
        $service->processLead($this->integrationId, $this->pageId, $this->leadId);
    }
}

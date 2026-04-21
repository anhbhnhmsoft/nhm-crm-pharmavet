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

    public int $facebookLeadId;

    public int $tries = 5;
    public array $backoff = [60, 120, 300, 600, 900];

    public function __construct(int $facebookLeadId)
    {
        $this->facebookLeadId = $facebookLeadId;
    }

    public function handle(MetaBusinessService $service)
    {
        $result = $service->processQueuedLead($this->facebookLeadId);

        if ($result->isSuccess()) {
            return;
        }

        $retryable = data_get($result->getData(), 'retryable', true);
        if ($retryable === false) {
            return;
        }

        throw new \RuntimeException($result->getMessage());
    }
}

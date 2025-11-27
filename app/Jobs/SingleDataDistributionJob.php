<?php

namespace App\Jobs;

use App\Services\CustomerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SingleDataDistributionJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        public int $customerId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CustomerService $customerService): void
    {
        try {
            Log::info('Starting single customer distribution', [
                'customer_id' => $this->customerId,
                'attempt' => $this->attempts(),
            ]);

            $result = $customerService->distributionSingleCustomer($this->customerId);

            if ($result->isSuccess()) {
                Log::info('Customer distribution completed successfully', [
                    'customer_id' => $this->customerId,
                    'data' => $result->getData(),
                ]);
            } else {
                Log::error('Customer distribution failed (Business Logic Error)', [
                    'customer_id' => $this->customerId,
                    'error' => $result->getMessage(),
                ]);

                $this->fail(new \Exception($result->getMessage()));
            }
        } catch (Throwable $e) {
            Log::error('Exception in SingleDataDistributionJob', [
                'customer_id' => $this->customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff);
            } else {
                $this->fail($e);
            }
        }
    }

}

<?php

namespace App\Jobs;

use App\Common\Constants\Order\OrderStatus;
use App\Models\Order;
use App\Services\GHNService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessGHNOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [10, 30, 60]; // Retry after 10s, 30s, 60s

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order,
        public string $action, // 'post' or 'cancel'
        public array $data = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $orderService = app(\App\Services\OrderService::class);

        try {
            if ($this->action === 'post') {
                $orderService->processPostOrder($this->order);
            } elseif ($this->action === 'cancel') {
                $orderService->processCancelOrder($this->order);
            }
        } catch (\Exception $e) {
            Log::error('ProcessGHNOrderJob failed', [
                'order_id' => $this->order->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessGHNOrderJob permanently failed', [
            'order_id' => $this->order->id,
            'action' => $this->action,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify admin or update order status
        // You can send notification here
    }
}

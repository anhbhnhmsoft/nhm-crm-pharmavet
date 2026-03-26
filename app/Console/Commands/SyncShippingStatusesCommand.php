<?php

namespace App\Console\Commands;

use App\Jobs\Warehouse\SyncShippingStatusesJob;
use Illuminate\Console\Command;

class SyncShippingStatusesCommand extends Command
{
    protected $signature = 'app:sync-shipping-statuses {--organization_id=}';

    protected $description = 'Sync shipping statuses from GHN for shipping orders';

    public function handle(): int
    {
        $organizationId = $this->option('organization_id');

        SyncShippingStatusesJob::dispatch($organizationId ? (int) $organizationId : null)
            ->onQueue('shipping_sync');

        $this->info('Shipping status sync job dispatched.');

        return self::SUCCESS;
    }
}

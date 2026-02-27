<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class SyncExchangeRateCommand extends Command
{
    protected $signature = 'app:sync-exchange-rate {--date= : Ngày đồng bộ định dạng Y-m-d}';

    protected $description = 'Đồng bộ tỷ giá USD -> VND cho các tổ chức nước ngoài';

    public function __construct(
        protected ExchangeRateService $exchangeRateService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date');

        $syncedCount = $this->exchangeRateService->syncForAllForeignOrganizations(
            is_string($date) && $date !== '' ? $date : null
        );

        $this->info('Synced exchange rates: ' . $syncedCount);

        return self::SUCCESS;
    }
}


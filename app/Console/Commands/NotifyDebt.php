<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DebtNotificationService;

class NotifyDebt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notify-debt {--threshold=3 : Số ngày nợ tối thiểu để cảnh báo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động quét và gửi thông báo nhắc nợ cho Sale phụ trách';

    /**
     * Execute the console command.
     */
    public function handle(DebtNotificationService $debtService)
    {
        $this->info('Starting debt notification check...');

        $threshold = (int) $this->option('threshold');

        $debtService->notifyOverdueDebts($threshold);

        $this->info('Debt notification check completed.');
    }
}

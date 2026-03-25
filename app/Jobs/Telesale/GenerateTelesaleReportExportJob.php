<?php

namespace App\Jobs\Telesale;

use Filament\Notifications\Notification;
use App\Models\Order;
use App\Models\ReportExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateTelesaleReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $exportJobId)
    {
    }

    public function handle(): void
    {
        $exportJob = ReportExportJob::query()->find($this->exportJobId);
        if (!$exportJob) {
            return;
        }

        $exportJob->update(['status' => 'processing']);

        try {
            $filters = $exportJob->filters_json ?? [];
            $query = Order::query()->where('organization_id', $exportJob->user->organization_id);

            if (!empty($filters['from_date'])) {
                $query->whereDate('created_at', '>=', $filters['from_date']);
            }
            if (!empty($filters['to_date'])) {
                $query->whereDate('created_at', '<=', $filters['to_date']);
            }

            $rows = $query->latest('created_at')->get(['code', 'customer_id', 'status', 'total_amount', 'collect_amount', 'created_at']);

            $filePath = 'exports/telesale_report_' . $exportJob->id . '.csv';
            $csv = "code,customer_id,status,total_amount,collect_amount,created_at\n";
            foreach ($rows as $row) {
                $csv .= implode(',', [
                    $row->code,
                    $row->customer_id,
                    $row->status,
                    $row->total_amount,
                    $row->collect_amount,
                    $row->created_at,
                ]) . "\n";
            }

            Storage::disk('local')->put($filePath, $csv);

            $exportJob->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'completed_at' => now(),
            ]);

            Notification::make()
                ->title(__('telesale.reports.export_completed'))
                ->body($filePath)
                ->success()
                ->sendToDatabase($exportJob->user);
        } catch (\Throwable $exception) {
            $exportJob->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title(__('telesale.reports.export_failed'))
                ->body($exception->getMessage())
                ->danger()
                ->sendToDatabase($exportJob->user);
        }
    }
}

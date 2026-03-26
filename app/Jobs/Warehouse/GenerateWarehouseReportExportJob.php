<?php

namespace App\Jobs\Warehouse;

use App\Models\ReportExportJob;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateWarehouseReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $exportJobId)
    {
    }

    public function handle(): void
    {
        $job = ReportExportJob::query()->find($this->exportJobId);
        if (!$job) {
            return;
        }

        $job->update(['status' => 'processing']);

        try {
            $filters = $job->filters_json ?? [];
            $csv = "report_type,from_date,to_date,generated_at\n";
            $csv .= implode(',', [
                $job->report_type,
                $filters['from_date'] ?? '',
                $filters['to_date'] ?? '',
                now()->toDateTimeString(),
            ]) . "\n";

            $path = 'exports/warehouse_report_' . $job->id . '.csv';
            Storage::disk('local')->put($path, $csv);

            $job->update([
                'status' => 'completed',
                'file_path' => $path,
                'completed_at' => now(),
            ]);

            Notification::make()
                ->title(__('telesale.reports.export_completed'))
                ->body($path)
                ->success()
                ->sendToDatabase($job->user);
        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Jobs\Telesale;

use App\Exports\SimpleArrayExport;
use App\Models\ReportExportJob;
use App\Services\Telesale\TelesaleReportDataService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

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
            /** @var TelesaleReportDataService $reportDataService */
            $reportDataService = app(TelesaleReportDataService::class);
            $dataset = $reportDataService->buildExportDataset(
                reportType: (string) $exportJob->report_type,
                user: $exportJob->user,
                filters: $filters,
            );

            $filePath = 'exports/telesale_' . $exportJob->report_type . '_' . $exportJob->id . '.xlsx';
            Excel::store(
                new SimpleArrayExport($dataset['headers'], $dataset['rows']),
                $filePath,
                'local'
            );

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

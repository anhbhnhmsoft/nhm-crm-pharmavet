<?php

namespace App\Services\Warehouse;

use App\Jobs\Warehouse\GenerateWarehouseReportExportJob;
use App\Models\ReportExportJob;
use App\Models\User;

class WarehouseExportService
{
    public function enqueueExport(User $user, string $reportType, array $filters, int $rowCount): ReportExportJob
    {
        $exportJob = ReportExportJob::query()->create([
            'user_id' => $user->id,
            'report_type' => $reportType,
            'filters_json' => $filters,
            'row_count' => $rowCount,
            'status' => 'pending',
        ]);

        if ($rowCount > 5000) {
            GenerateWarehouseReportExportJob::dispatch($exportJob->id)->onQueue('report_export');
        } else {
            (new GenerateWarehouseReportExportJob($exportJob->id))->handle();
        }

        return $exportJob;
    }
}

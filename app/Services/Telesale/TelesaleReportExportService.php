<?php

namespace App\Services\Telesale;

use App\Jobs\Telesale\GenerateTelesaleReportExportJob;
use App\Models\ReportExportJob;
use App\Models\User;

class TelesaleReportExportService
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
            GenerateTelesaleReportExportJob::dispatch($exportJob->id);
        } else {
            (new GenerateTelesaleReportExportJob($exportJob->id))->handle();
        }

        return $exportJob;
    }
}

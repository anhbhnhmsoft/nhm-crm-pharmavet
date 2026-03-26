<?php

namespace App\Filament\Clusters\Accounting\Resources\DiscrepancyReportResource\Pages;

use App\Filament\Clusters\Accounting\Resources\DiscrepancyReportResource;
use Filament\Resources\Pages\ListRecords;

class ListDiscrepancyReports extends ListRecords
{
    protected static string $resource = DiscrepancyReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return __('accounting.report.discrepancy');
    }
}

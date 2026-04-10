<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Widgets;

use App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages\ListReconciliations;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class ReconciliationStatsWidget extends BaseWidget
{
    use InteractsWithPageTable;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getTablePage(): string
    {
        return ListReconciliations::class;
    }

    protected function getStats(): array
    {
        $currentPageQuery = $this->getPageTableRecords();
        $allRecordsQuery = $this->getPageTableQuery();

        $currentPageTotal = ($currentPageQuery && method_exists($currentPageQuery, 'sum')) ? $currentPageQuery->sum('total_fee') : 0;
        $allTotal = ($allRecordsQuery && method_exists($allRecordsQuery, 'sum')) ? $allRecordsQuery->sum('total_fee') : 0;

        return [
            Stat::make(__('accounting.reconciliation.summary.page_total'), number_format($currentPageTotal, 0, ',', '.') . ' đ')
                ->description(__('accounting.reconciliation.summary.page_total_desc'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make(__('accounting.reconciliation.summary.all_total'), number_format($allTotal, 0, ',', '.') . ' đ')
                ->description(__('accounting.reconciliation.summary.all_total_desc'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('success'),
        ];
    }
}

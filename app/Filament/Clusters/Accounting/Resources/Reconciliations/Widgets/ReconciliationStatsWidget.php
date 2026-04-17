<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Widgets;

use App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages\ListReconciliations;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\DB;

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
        $pageRecords = $this->getPageTableRecords();

        if (is_object($pageRecords) && method_exists($pageRecords, 'getCollection')) {
            $pageRecords = $pageRecords->getCollection();
        } elseif (is_object($pageRecords) && method_exists($pageRecords, 'items')) {
            $pageRecords = $pageRecords->items();
        }

        $currentPageTotal = collect($pageRecords ?? [])
            ->sum(fn($record) => (float) ($record->order?->total_amount ?? $record->cod_amount ?? 0));

        $allRecordsQuery = $this->getPageTableQuery();
        $allTotal = 0;

        if ($allRecordsQuery) {
            $allTotal = (float) (clone $allRecordsQuery)
                ->leftJoin('orders', 'orders.id', '=', 'reconciliations.order_id')
                ->sum(DB::raw('COALESCE(orders.total_amount, reconciliations.cod_amount)'));
        }

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

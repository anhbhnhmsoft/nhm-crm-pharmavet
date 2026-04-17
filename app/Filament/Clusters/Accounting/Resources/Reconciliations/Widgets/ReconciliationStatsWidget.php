<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Widgets;

use App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages\ListReconciliations;
use App\Models\Reconciliation;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;

class ReconciliationStatsWidget extends BaseWidget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getTablePage(): string
    {
        return ListReconciliations::class;
    }

    protected function resolveDisplayedAmount(Model $record): float
    {
        /** @var \App\Models\Reconciliation $record */
        return (float) ($record->order?->total_amount ?? $record->cod_amount ?? 0);
    }

    protected function getCachedStats(): array
    {
        return $this->getStats();
    }

    protected function getStats(): array
    {
        if (isset($this->tablePage)) {
            unset($this->tablePage);
        }

        $filteredTotal = 0;
        $allTotal = 0;

        try {
            $filteredRecords = $this->getTablePageInstance()
                ->getFilteredTableQuery()
                ?->select('reconciliations.*')
                ->with(['order:id,total_amount,deleted_at'])
                ->lazy(200);

            if ($filteredRecords !== null) {
                foreach ($filteredRecords as $record) {
                    $filteredTotal += $this->resolveDisplayedAmount($record);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $allRecords = Reconciliation::query()
                ->where('organization_id', Auth::user()?->organization_id)
                ->select('reconciliations.*')
                ->with(['order:id,total_amount,deleted_at'])
                ->lazy(200);

            foreach ($allRecords as $record) {
                $allTotal += $this->resolveDisplayedAmount($record);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            Stat::make(
                new HtmlString(
                    e(__('accounting.reconciliation.summary.page_total')) .
                    ' <span class="text-primary-500 cursor-help" title="' .
                    e(__('accounting.reconciliation.summary.page_total_formula')) .
                    '">&#9432;</span>'
                ),
                number_format((float) $filteredTotal, 0, ',', '.') . ' VND'
            )
                ->description(__('accounting.reconciliation.summary.page_total_desc'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make(
                new HtmlString(
                    e(__('accounting.reconciliation.summary.all_total')) .
                    ' <span class="text-primary-500 cursor-help" title="' .
                    e(__('accounting.reconciliation.summary.all_total_formula')) .
                    '">&#9432;</span>'
                ),
                number_format((float) $allTotal, 0, ',', '.') . ' VND'
            )
                ->description(__('accounting.reconciliation.summary.all_total_desc'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('success'),
        ];
    }
}

<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Widgets;

use App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages\ListReconciliations;
use App\Services\ReconciliationService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;

class ReconciliationStatsWidget extends BaseWidget
{
    use InteractsWithPageTable;

    protected const CACHE_VERSION = 'v2';

    protected static bool $isLazy = true;

    protected ?string $cachedStatsKey = null;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getTablePage(): string
    {
        return ListReconciliations::class;
    }

    protected function resolveDisplayedAmountSum(Builder $query): float
    {
        return app(ReconciliationService::class)->sumDisplayedAmount($query);
    }

    protected function getStatsStateHash(): string
    {
        return md5(json_encode([
            'active_tab' => $this->activeTab,
            'filters' => $this->tableFilters,
            'search' => $this->tableSearch,
            'column_searches' => $this->tableColumnSearches,
        ], JSON_UNESCAPED_UNICODE));
    }

    protected function getFilteredTotalCacheKey(): string
    {
        return sprintf(
            'reconciliations.stats.%s.filtered.%s.%s',
            static::CACHE_VERSION,
            Auth::user()?->organization_id ?? 'guest',
            $this->getStatsStateHash(),
        );
    }

    protected function getAllTotalCacheKey(): string
    {
        return sprintf(
            'reconciliations.stats.%s.all.%s',
            static::CACHE_VERSION,
            Auth::user()?->organization_id ?? 'guest',
        );
    }

    protected function getCachedStats(): array
    {
        $cacheKey = $this->getStatsStateHash();

        if (($this->cachedStats !== null) && ($this->cachedStatsKey === $cacheKey)) {
            return $this->cachedStats;
        }

        $this->cachedStatsKey = $cacheKey;

        return $this->cachedStats = $this->getStats();
    }

    protected function getStats(): array
    {
        $filteredTotal = 0.0;
        $allTotal = 0.0;

        try {
            $filteredTotal = (float) Cache::remember(
                $this->getFilteredTotalCacheKey(),
                now()->addSeconds(20),
                function (): float {
                    if (isset($this->tablePage)) {
                        unset($this->tablePage);
                    }

                    $filteredQuery = $this->getTablePageInstance()->getFilteredTableQuery();

                    if ($filteredQuery === null) {
                        return 0.0;
                    }

                    return $this->resolveDisplayedAmountSum($filteredQuery);
                },
            );
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $allTotal = (float) Cache::remember(
                $this->getAllTotalCacheKey(),
                now()->addMinutes(1),
                fn (): float => app(ReconciliationService::class)->getAllDisplayedAmount(Auth::user()?->organization_id),
            );
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

<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Widgets;

use App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages\ListReconciliations;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use LogicException;
use function Livewire\trigger;

class ReconciliationStatsWidget extends BaseWidget
{
    /** 
     */
    #[Reactive]
    public $paginators = [];

    #[Reactive]
    public ?int $tableRecordsCount = null;

    #[Reactive]
    public $tableColumnSearches = [];

    #[Reactive]
    public ?string $tableGrouping = null;

    #[Reactive]
    public $tableFilters = null;

    #[Reactive]
    public int | string | null $tableRecordsPerPage = null;

    #[Reactive]
    public $tableSearch = '';

    #[Reactive]
    public ?string $tableSort = null;

    #[Reactive]
    public ?string $activeTab = null;

    #[Reactive] #[Locked]
    public ?Model $parentRecord = null;

    protected HasTable $tablePage;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getTablePage(): string
    {
        return ListReconciliations::class;
    }

    protected function getTablePageInstance(): HasTable
    {
        if (isset($this->tablePage)) {
            return $this->tablePage;
        }

        $page = app('livewire')->new($this->getTablePage());

        trigger('mount', $page, [], null, null);

        foreach ([
            'activeTab' => $this->activeTab,
            'paginators' => $this->paginators,
            'parentRecord' => $this->parentRecord,
            'tableColumnSearches' => $this->tableColumnSearches ?? [],
            'tableFilters' => $this->tableFilters,
            'tableGrouping' => $this->tableGrouping,
            'tableRecordsPerPage' => $this->tableRecordsPerPage,
            'tableSearch' => $this->tableSearch,
            'tableSort' => $this->tableSort,
        ] as $property => $value) {
            $page->{$property} = $value;
        }

        $page->bootedInteractsWithTable();

        return $this->tablePage = $page;
    }

    protected function getPageTableQuery(): Builder
    {
        return $this->getTablePageInstance()->getFilteredSortedTableQuery();
    }

    protected function getPageTableRecords(): Collection | Paginator
    {
        return $this->getTablePageInstance()->getTableRecords();
    }

    protected function getStats(): array
    {
        try {
            $currentPageQuery = $this->getPageTableRecords();
            $allRecordsQuery  = $this->getPageTableQuery();

            $currentPageTotal = ($currentPageQuery && method_exists($currentPageQuery, 'sum'))
                ? $currentPageQuery->sum('total_fee')
                : 0;

            $allTotal = ($allRecordsQuery && method_exists($allRecordsQuery, 'sum'))
                ? $allRecordsQuery->sum('total_fee')
                : 0;
        } catch (\Throwable) {
            $currentPageTotal = 0;
            $allTotal         = 0;
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

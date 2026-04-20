<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Exports\ReconciliationReportExport;
use App\Filament\Clusters\Accounting\Resources\Reconciliations\ReconciliationResource;
use App\Filament\Clusters\Accounting\Resources\Reconciliations\Widgets\ReconciliationStatsWidget;
use App\Services\ReconciliationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ListReconciliations extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ReconciliationResource::class;

    protected bool $hasSentInvalidDateRangeNotification = false;

    protected function hasInvalidDateRange(?array $filters): bool
    {
        $from = data_get($filters, 'date_range.from');
        $to = data_get($filters, 'date_range.to');

        if (blank($from) || blank($to)) {
            return false;
        }

        try {
            return Carbon::parse($from)->gt(Carbon::parse($to));
        } catch (\Throwable) {
            return false;
        }
    }

    protected function clearDateRange(?array $filters): ?array
    {
        $filters ??= [];

        Arr::forget($filters, [
            'date_range.from',
            'date_range.to',
        ]);

        if (blank(data_get($filters, 'date_range'))) {
            Arr::forget($filters, 'date_range');
        }

        return blank($filters) ? null : $filters;
    }

    protected function notifyInvalidDateRange(): void
    {
        if ($this->hasSentInvalidDateRangeNotification) {
            return;
        }

        $this->hasSentInvalidDateRangeNotification = true;

        Notification::make()
            ->danger()
            ->title(__('accounting.reconciliation.error'))
            ->body(__('accounting.reconciliation.filter_date_invalid_range'))
            ->send();
    }

    protected function resetInvalidDateRangeFilter(bool $notify = true): bool
    {
        if (! $this->hasInvalidDateRange($this->tableFilters)) {
            return false;
        }

        $this->tableFilters = $this->clearDateRange($this->tableFilters);
        $this->tableDeferredFilters = $this->clearDateRange($this->tableDeferredFilters);

        $this->getTableFiltersForm()->fill($this->tableDeferredFilters ?? $this->tableFilters ?? []);
        $this->handleTableFilterUpdates();

        if ($notify) {
            $this->notifyInvalidDateRange();
        }

        return true;
    }

    protected function resetInvalidDeferredDateRangeFilter(bool $notify = true): bool
    {
        if (! $this->hasInvalidDateRange($this->tableDeferredFilters)) {
            return false;
        }

        $this->tableDeferredFilters = $this->clearDateRange($this->tableDeferredFilters);
        $this->getTableFiltersForm()->fill($this->tableDeferredFilters ?? []);

        if ($notify) {
            $this->notifyInvalidDateRange();
        }

        return true;
    }

    protected function resolveSyncGhnConfigState(): array
    {
        return app(ReconciliationService::class)->getSyncGhnConfigState(Auth::user()->organization_id);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReconciliationStatsWidget::class,
        ];
    }

    public function bootedInteractsWithTable(): void
    {
        parent::bootedInteractsWithTable();

        $this->resetInvalidDateRangeFilter();
    }

    public function updatedTableFilters(): void
    {
        if ($this->resetInvalidDateRangeFilter()) {
            return;
        }

        parent::updatedTableFilters();
    }

    public function applyTableFilters(): void
    {
        if ($this->resetInvalidDeferredDateRangeFilter()) {
            return;
        }

        parent::applyTableFilters();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_ghn')
                ->label(__('accounting.reconciliation.sync_from_ghn'))
                ->icon('heroicon-o-arrow-path')
                ->disabled(fn () => ! $this->resolveSyncGhnConfigState()['ready'])
                ->tooltip(fn () => $this->resolveSyncGhnConfigState()['tooltip'])
                ->form([
                    DatePicker::make('from_date')
                        ->label(__('accounting.reconciliation.from_date'))
                        ->required()
                        ->default(now()->subDays(7)),
                    DatePicker::make('to_date')
                        ->label(__('accounting.reconciliation.to_date'))
                        ->required()
                        ->default(now())
                        ->after('from_date'),
                ])
                ->action(function (array $data, ReconciliationService $service): void {
                    $result = $service->syncReconciliationFromGHN(
                        organizationId: Auth::user()->organization_id,
                        fromDate: $data['from_date'],
                        toDate: $data['to_date'],
                    );

                    if ($result->isError()) {
                        Notification::make()
                            ->danger()
                            ->title(__('accounting.reconciliation.sync_failed'))
                            ->body($result->getMessage())
                            ->send();

                        return;
                    }

                    $backfilledCount = $service->applyExchangeRateForDateRange(
                        organizationId: Auth::user()->organization_id,
                        fromDate: $data['from_date'],
                        toDate: $data['to_date'],
                    );

                    Notification::make()
                        ->success()
                        ->title(__('accounting.reconciliation.synced', [
                            'count' => ($result->getData()['created'] ?? 0) + ($result->getData()['updated'] ?? 0),
                        ]))
                        ->body(__('accounting.reconciliation.exchange_rate_auto_attached', ['count' => $backfilledCount]))
                        ->send();

                    $this->dispatch('$refresh');
                }),
            Action::make('batch_reconciliation')
                ->label(__('accounting.reconciliation.batch_reconciliation'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label(__('accounting.reconciliation.upload_excel'))
                        ->acceptedFileTypes([
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                        ])
                        ->required()
                        ->extraInputAttributes(['required' => false])
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ])
                        ->disk('local')
                        ->directory('temp_imports'),
                    Placeholder::make('note')
                        ->content(__('accounting.reconciliation.import.upload_placeholder')),
                ])
                ->action(function (array $data, ReconciliationService $service): void {
                    $result = $service->importBatchReconciliationFromUploadedFile(
                        organizationId: Auth::user()->organization_id,
                        disk: 'local',
                        path: (string) $data['file'],
                    );

                    if ($result->isError()) {
                        Notification::make()
                            ->danger()
                            ->title(__('accounting.reconciliation.import.process_error'))
                            ->body($result->getMessage())
                            ->send();

                        return;
                    }

                    $summary = $result->getData();

                    Notification::make()
                        ->success()
                        ->title(__('accounting.reconciliation.batch_success', ['count' => $summary['updated'] ?? 0]))
                        ->body(__('accounting.reconciliation.import.batch_summary_body', [
                            'updated' => $summary['updated'] ?? 0,
                            'skipped' => $summary['skipped'] ?? 0,
                            'not_found' => count($summary['not_found'] ?? []),
                        ]))
                        ->send();

                    $this->dispatch('$refresh');
                }),
            Action::make('export_excel')
                ->label(__('accounting.reconciliation.export_excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function (ReconciliationService $service) {
                    return Excel::download(
                        new ReconciliationReportExport(
                            $service->getExportRowsForQuery($this->getFilteredTableQuery())
                        ),
                        'doi-soat-' . now()->format('YmdHis') . '.xlsx'
                    );
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('accounting.reconciliation_status.all')),
            strtolower(ReconciliationStatus::PENDING->name) => Tab::make(__('accounting.reconciliation_status.pending'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReconciliationStatus::PENDING->value)),
            strtolower(ReconciliationStatus::CONFIRMED->name) => Tab::make(__('accounting.reconciliation_status.confirmed'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReconciliationStatus::CONFIRMED->value)),
            strtolower(ReconciliationStatus::CANCELLED->name) => Tab::make(__('accounting.reconciliation_status.cancelled'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReconciliationStatus::CANCELLED->value)),
            strtolower(ReconciliationStatus::PAID->name) => Tab::make(__('accounting.reconciliation_status.paid'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReconciliationStatus::PAID->value)),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}

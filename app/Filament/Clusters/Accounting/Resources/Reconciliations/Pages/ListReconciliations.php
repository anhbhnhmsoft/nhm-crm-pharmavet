<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages;

use App\Filament\Clusters\Accounting\Resources\Reconciliations\ReconciliationResource;
use App\Repositories\ShippingConfigRepository;
use App\Services\ReconciliationService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Core\Logging;
use Maatwebsite\Excel\Facades\Excel;
use Storage;

class ListReconciliations extends ListRecords
{
    protected static string $resource = ReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_ghn')
                ->label(__('accounting.reconciliation.sync_from_ghn'))
                ->icon('heroicon-o-arrow-path')
                ->disabled(function (ShippingConfigRepository $shippingConfigRepo) {
                    $config = $shippingConfigRepo->query()
                        ->where('organization_id', Auth::user()->organization_id)
                        ->first();

                    return !($config && !empty($config->api_token) && !empty($config->default_store_id));
                })
                ->tooltip(function (ShippingConfigRepository $shippingConfigRepo) {
                    $config = $shippingConfigRepo->query()
                        ->where('organization_id', Auth::user()->organization_id)
                        ->first();
                    $hasConfig = $config && !empty($config->api_token) && !empty($config->default_store_id);
                    return !$hasConfig ? __('accounting.reconciliation.config_not_found') : null;
                })
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
                ->action(function (array $data, ReconciliationService $service) {
                    $result = $service->syncReconciliationFromGHN(
                        organizationId: Auth::user()->organization_id,
                        fromDate: $data['from_date'],
                        toDate: $data['to_date']
                    );

                    if ($result->isError()) {
                        Notification::make()
                            ->danger()
                            ->title(__('accounting.reconciliation.sync_failed'))
                            ->body($result->getMessage())
                            ->send();
                    } else {
                        $backfilledCount = $service->applyExchangeRateForDateRange(
                            organizationId: Auth::user()->organization_id,
                            fromDate: $data['from_date'],
                            toDate: $data['to_date']
                        );

                        Notification::make()
                            ->success()
                            ->title(__('accounting.reconciliation.synced', ['count' => ($result->getData()['created'] ?? 0) + ($result->getData()['updated'] ?? 0)]))
                            ->body(__('accounting.reconciliation.exchange_rate_auto_attached', ['count' => $backfilledCount]))
                            ->send();

                        $this->dispatch('$refresh');
                    }
                }),
            Action::make('batch_reconciliation')
                ->label(__('accounting.reconciliation.batch_reconciliation'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label(__('accounting.reconciliation.upload_excel'))
                        ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                        ->required()
                        ->disk('local')
                        ->directory('temp_imports'),
                    Placeholder::make('note')
                        ->content(__('accounting.reconciliation.import.upload_placeholder'))
                ])
                ->action(function (array $data, ReconciliationService $service) {
                    $disk = 'local';
                    $filePath = Storage::disk($disk)->path($data['file']);

                    try {
                        if (!Storage::disk($disk)->exists($data['file'])) {
                            throw new \Exception(__('accounting.reconciliation.import.file_not_found'));
                        }

                        $rows = Excel::toArray(new class {}, $filePath);
                        $sheet = $rows[0] ?? [];

                        if (empty($sheet)) {
                            throw new \Exception(__('accounting.reconciliation.import.file_empty'));
                        }

                        $header = array_shift($sheet);
                        $normalizedHeader = array_map(fn($h) => trim(mb_strtolower((string) $h)), $header);

                        $requiredHeaders = __('accounting.reconciliation.import.headers');

                        $colMapping = [];
                        foreach ($requiredHeaders as $key => $aliases) {
                            foreach ($aliases as $alias) {
                                $idx = array_search(trim(mb_strtolower($alias)), $normalizedHeader);
                                if ($idx !== false) {
                                    $colMapping[$key] = $idx;
                                    break;
                                }
                            }
                        }

                        $missing = [];
                        foreach ($requiredHeaders as $key => $aliases) {
                            if (!isset($colMapping[$key])) {
                                $missing[] = '"' . ($aliases[0] ?? $key) . '"';
                            }
                        }

                        if (!empty($missing)) {
                            throw new \Exception(__('accounting.reconciliation.import.missing_columns', ['columns' => implode(', ', $missing)]));
                        }

                        $items = [];
                        $statusKeywords = __('accounting.reconciliation.import.status_keywords');

                        foreach ($sheet as $row) {
                            $code = trim((string) ($row[$colMapping['ghn_code']] ?? ''));
                            $statusText = trim(mb_strtolower((string) ($row[$colMapping['status']] ?? '')));

                            if (empty($code)) {
                                continue;
                            }

                            $targetStatus = null;
                            foreach ($statusKeywords['confirmed'] as $kw) {
                                if (str_contains($statusText, mb_strtolower($kw))) {
                                    $targetStatus = ReconciliationStatus::CONFIRMED->value;
                                    break;
                                }
                            }

                            if (!$targetStatus) {
                                foreach ($statusKeywords['paid'] as $kw) {
                                    if (str_contains($statusText, mb_strtolower($kw))) {
                                        $targetStatus = ReconciliationStatus::PAID->value;
                                        break;
                                    }
                                }
                            }

                            if ($targetStatus) {
                                $items[] = [
                                    'ghn_order_code' => $code,
                                    'target_status' => $targetStatus,
                                    'cod_amount' => (float) str_replace([',', '.'], '', $row[$colMapping['cod']] ?? 0),
                                    'shipping_fee' => (float) str_replace([',', '.'], '', $row[$colMapping['shipping']] ?? 0),
                                    'total_fee' => (float) str_replace([',', '.'], '', $row[$colMapping['total']] ?? 0),
                                    'reconciliation_date' => trim((string) ($row[$colMapping['reconciliation_date']] ?? '')),
                                    'ghn_employee_note' => trim((string) ($row[$colMapping['note']] ?? '')),
                                    'ghn_to_name' => trim((string) ($row[$colMapping['name']] ?? '')),
                                    'ghn_to_phone' => trim((string) ($row[$colMapping['phone']] ?? '')),
                                    'ghn_to_address' => trim((string) ($row[$colMapping['address']] ?? '')),
                                ];
                            }
                        }

                        if (empty($items)) {
                            throw new \Exception(__('accounting.reconciliation.import.no_valid_data'));
                        }

                        $result = $service->processBatchReconciliation(
                            organizationId: Auth::user()->organization_id,
                            items: $items
                        );

                        if ($result->isError()) {
                            Notification::make()
                                ->danger()
                                ->title(__('accounting.reconciliation.batch_failed'))
                                ->body($result->getMessage())
                                ->send();
                        } else {
                            $resData = $result->getData();
                            Notification::make()
                                ->success()
                                ->title(__('accounting.reconciliation.batch_success', ['count' => $resData['updated']]))
                                ->body(__('accounting.reconciliation.import.batch_summary_body', [
                                    'updated' => $resData['updated'],
                                    'skipped' => $resData['skipped'],
                                    'not_found' => count($resData['not_found']),
                                ]))
                                ->send();

                            if (!empty($resData['not_found'])) {
                                Logging::web('Batch reconciliation unmatched codes from Excel', [
                                    'not_found' => $resData['not_found']
                                ]);
                            }

                            $this->dispatch('$refresh');
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title(__('accounting.reconciliation.import.process_error'))
                            ->body($e->getMessage())
                            ->send();
                    } finally {
                        if (Storage::disk($disk)->exists($data['file'])) {
                            Storage::disk($disk)->delete($data['file']);
                        }
                    }
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('accounting.reconciliation_status.all')),
            'pending' => Tab::make(__('accounting.reconciliation_status.pending'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ReconciliationStatus::PENDING->value)),
            'confirmed' => Tab::make(__('accounting.reconciliation_status.confirmed'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ReconciliationStatus::CONFIRMED->value)),
            'cancelled' => Tab::make(__('accounting.reconciliation_status.cancelled'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ReconciliationStatus::CANCELLED->value)),
            'paid' => Tab::make(__('accounting.reconciliation_status.paid'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ReconciliationStatus::PAID->value)),
        ];
    }
}

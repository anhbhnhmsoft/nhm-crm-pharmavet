<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages;

use App\Filament\Clusters\Accounting\Resources\Reconciliations\ReconciliationResource;
use App\Repositories\ShippingConfigRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Services\ReconciliationService;
use Illuminate\Support\Facades\Auth;

class ListReconciliations extends ListRecords
{
    protected static string $resource = ReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        // Kiểm tra xem có config GHN chưa
        $shippingConfigRepo = app(ShippingConfigRepository::class);
        $config = $shippingConfigRepo->query()
            ->where('organization_id', Auth::user()->organization_id)
            ->first();

        $hasConfig = $config && !empty($config->api_token) && !empty($config->default_store_id);

        return [
            Action::make('sync_ghn')
                ->label(__('accounting.reconciliation.sync_from_ghn'))
                ->icon('heroicon-o-arrow-path')
                ->disabled(!$hasConfig)
                ->tooltip(!$hasConfig ? __('accounting.reconciliation.config_not_found') : null)
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
                ->action(function (array $data) {
                    $service = app(ReconciliationService::class);
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
                        Notification::make()
                            ->success()
                            ->title(__('accounting.reconciliation.synced', ['count' => ($result->getData()['created'] ?? 0) + ($result->getData()['updated'] ?? 0)]))
                            ->send();
                        
                        $this->dispatch('$refresh');
                    }
                }),
        ];
    }
}


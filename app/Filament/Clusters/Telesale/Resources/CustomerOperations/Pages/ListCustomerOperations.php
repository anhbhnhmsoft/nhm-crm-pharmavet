<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations\Pages;

use App\Filament\Clusters\Telesale\Resources\CustomerOperations\CustomerOperationResource;
use App\Services\GHNService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCustomerOperations extends ListRecords
{
    protected static string $resource = CustomerOperationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_ghn_shops')
                ->label(__('filament.shipping.sync_shops'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    try {
                        $user = Auth::user();
                        $ghnService = app(GHNService::class, [
                            'organizationId' => $user->organization_id
                        ]);
                        
                        $shops = $ghnService->syncShopsToDatabase($user->organization_id);
                        
                        Notification::make()
                            ->title(__('filament.shipping.sync_success'))
                            ->body(__('filament.shipping.found_shops', ['count' => count($shops)]))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('filament.shipping.sync_error'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

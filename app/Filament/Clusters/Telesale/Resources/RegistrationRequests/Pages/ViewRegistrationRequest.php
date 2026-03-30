<?php

namespace App\Filament\Clusters\Telesale\Resources\RegistrationRequests\Pages;

use App\Filament\Clusters\Telesale\Resources\RegistrationRequests\RegistrationRequestResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRegistrationRequest extends ViewRecord
{
    protected static string $resource = RegistrationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('telesale.actions.back'))
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
            EditAction::make()
                ->label(__('telesale.actions.edit')),
        ];
    }
}

<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages;

use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\TelesaleOperationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelesaleOperations extends ListRecords
{
    protected static string $resource = TelesaleOperationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('telesale.actions.add_data')),
        ];
    }
}

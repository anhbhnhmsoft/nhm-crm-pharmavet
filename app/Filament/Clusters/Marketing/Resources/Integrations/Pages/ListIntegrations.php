<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations\Pages;

use App\Filament\Clusters\Marketing\Resources\Integrations\IntegrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIntegrations extends ListRecords
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

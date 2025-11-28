<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations\Pages;

use App\Filament\Clusters\Telesale\Resources\CustomerOperations\CustomerOperationResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerOperations extends ListRecords
{
    protected static string $resource = CustomerOperationResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}

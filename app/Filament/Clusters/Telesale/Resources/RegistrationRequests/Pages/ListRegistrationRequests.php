<?php

namespace App\Filament\Clusters\Telesale\Resources\RegistrationRequests\Pages;

use App\Filament\Clusters\Telesale\Resources\RegistrationRequests\RegistrationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListRegistrationRequests extends ListRecords
{
    protected static string $resource = RegistrationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}

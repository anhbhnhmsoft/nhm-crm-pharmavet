<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\Pages;

use App\Filament\Clusters\Accounting\Resources\Funds\FundResource;
use Filament\Resources\Pages\ListRecords;

class ListFunds extends ListRecords
{
    protected static string $resource = FundResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}

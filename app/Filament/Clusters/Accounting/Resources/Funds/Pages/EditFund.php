<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\Pages;

use App\Filament\Clusters\Accounting\Resources\Funds\FundResource;
use Filament\Resources\Pages\EditRecord;

class EditFund extends EditRecord
{
    protected static string $resource = FundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}

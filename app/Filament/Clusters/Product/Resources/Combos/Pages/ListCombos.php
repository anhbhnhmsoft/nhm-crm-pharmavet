<?php

namespace App\Filament\Clusters\Product\Resources\Combos\Pages;

use App\Filament\Clusters\Product\Resources\Combos\ComboResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCombos extends ListRecords
{
    protected static string $resource = ComboResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

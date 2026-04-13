<?php

namespace App\Filament\Clusters\Organization\Resources\CategoryProducts\Pages;

use App\Filament\Clusters\Organization\Resources\CategoryProducts\CategoryProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoryProducts extends ListRecords
{
    protected static string $resource = CategoryProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

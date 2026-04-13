<?php

namespace App\Filament\Clusters\Organization\Resources\CategoryProducts\Pages;

use App\Filament\Clusters\Organization\Resources\CategoryProducts\CategoryProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoryProduct extends EditRecord
{
    protected static string $resource = CategoryProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

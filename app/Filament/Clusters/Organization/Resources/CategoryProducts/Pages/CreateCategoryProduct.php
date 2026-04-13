<?php

namespace App\Filament\Clusters\Organization\Resources\CategoryProducts\Pages;

use App\Filament\Clusters\Organization\Resources\CategoryProducts\CategoryProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoryProduct extends CreateRecord
{
    protected static string $resource = CategoryProductResource::class;
}

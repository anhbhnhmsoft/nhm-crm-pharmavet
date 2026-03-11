<?php

namespace App\Filament\Clusters\Product\Resources\Products\Pages;

use App\Filament\Clusters\Product\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!isset($data['organization_id'])) {
            $data['organization_id'] = Auth::user()->organization_id;
        }

        return $data;
    }
}

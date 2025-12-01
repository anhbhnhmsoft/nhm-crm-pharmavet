<?php

namespace App\Filament\Clusters\Warehouse\Resources\Warehouses\Pages;
          
use App\Filament\Clusters\Warehouse\Resources\Warehouses\WarehouseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    public function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = Auth::user()->organization_id;
        return $data;
    }
}

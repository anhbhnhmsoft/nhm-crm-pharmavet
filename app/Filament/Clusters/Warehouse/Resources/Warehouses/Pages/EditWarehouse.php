<?php

namespace App\Filament\Clusters\Warehouse\Resources\Warehouses\Pages;

use App\Filament\Clusters\Warehouse\Resources\Warehouses\WarehouseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function mutateFormDataBeforeUpdate(array $data): array
    {
        $data['updated_by'] = Auth::user()->id;
        return $data;
    }
}

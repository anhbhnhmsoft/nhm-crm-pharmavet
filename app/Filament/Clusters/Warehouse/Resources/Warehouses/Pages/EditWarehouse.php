<?php

namespace App\Filament\Clusters\Warehouse\Resources\Warehouses\Pages;

use App\Filament\Clusters\Warehouse\Resources\Warehouses\WarehouseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        if (blank($data['code'] ?? null) && filled($data['name'] ?? null)) {
            $slug = substr(Str::slug($data['name'], '-'), 0, 3) . Str::random(5);
            $data['code'] = Str::upper($slug);
        }

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('common.success.update_success');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

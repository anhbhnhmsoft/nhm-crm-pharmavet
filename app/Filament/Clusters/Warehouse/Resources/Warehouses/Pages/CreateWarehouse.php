<?php

namespace App\Filament\Clusters\Warehouse\Resources\Warehouses\Pages;
          
use App\Filament\Clusters\Warehouse\Resources\Warehouses\WarehouseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;
    protected string $view = 'filament.clusters.warehouse.resources.warehouses.pages.create-warehouse';

    public function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = Auth::user()->organization_id;

        if (blank($data['code'] ?? null) && filled($data['name'] ?? null)) {
            $slug = substr(Str::slug($data['name'], '-'), 0, 3) . Str::random(5);
            $data['code'] = Str::upper($slug);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('common.success.add_success');
    }

    public function getSubheading(): ?string
    {
        return 'Thiet lap thong tin kho, khu vuc giao hang va ton kho ban dau trong cung mot man hinh.';
    }

    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'warehouse-create-page',
        ];
    }
}

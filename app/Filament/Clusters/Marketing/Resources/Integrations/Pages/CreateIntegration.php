<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations\Pages;

use App\Filament\Clusters\Marketing\Resources\Integrations\IntegrationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateIntegration extends CreateRecord
{
    protected static string $resource = IntegrationResource::class;

    public function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = Auth::user()->organization_id;
        $data['created_by'] = Auth::user()->id;
        return parent::mutateFormDataBeforeCreate($data);
    }
}

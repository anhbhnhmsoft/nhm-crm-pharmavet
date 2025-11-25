<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations\Pages;

use App\Filament\Clusters\Marketing\Resources\Integrations\IntegrationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditIntegration extends EditRecord
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function mutateFormDataBeforeFill(array $data): array
    {
        $data['organization_id'] = Auth::user()->organization_id;
        $data['updated_by'] = Auth::user()->id;
        return parent::mutateFormDataBeforeFill($data);
    }
}

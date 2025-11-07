<?php

namespace App\Filament\Clusters\Product\Resources\Combos\Pages;

use App\Filament\Clusters\Product\Resources\Combos\ComboResource;
use App\Services\ComboService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCombo extends EditRecord
{
    protected static string $resource = ComboResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

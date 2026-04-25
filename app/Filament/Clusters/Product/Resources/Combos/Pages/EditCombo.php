<?php

namespace App\Filament\Clusters\Product\Resources\Combos\Pages;

use App\Filament\Clusters\Product\Resources\Combos\ComboResource;
use App\Services\ComboService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCombo extends EditRecord
{
    protected static string $resource = ComboResource::class;

    protected function beforeSave(): void
    {
        $this->validateComboBusinessRules();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('filament.combo.updated_successfully');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function validateComboBusinessRules(): void
    {
        /** @var ComboService $comboService */
        $comboService = app(ComboService::class);
        $result = $comboService->validateComboPricing($this->form->getRawState()['productsPivot'] ?? []);

        if ($result->isSuccess()) {
            return;
        }

        throw ValidationException::withMessages([
            'data.productsPivot' => [$result->getMessage()],
        ]);
    }
}

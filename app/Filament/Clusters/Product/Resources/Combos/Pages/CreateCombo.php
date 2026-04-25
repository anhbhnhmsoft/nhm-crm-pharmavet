<?php

namespace App\Filament\Clusters\Product\Resources\Combos\Pages;

use App\Filament\Clusters\Product\Resources\Combos\ComboResource;
use App\Services\ComboService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateCombo extends CreateRecord
{
    protected static string $resource = ComboResource::class;

    protected function getRedirectUrl(): string
    {
        return ComboResource::getUrl('index');
    }

    protected function beforeCreate(): void
    {
        $this->validateComboBusinessRules();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()->id;
        $data['updated_by'] = Auth::user()->id;
        $data['organization_id'] = Auth::user()->organization_id;

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('filament.combo.created_successfully');
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

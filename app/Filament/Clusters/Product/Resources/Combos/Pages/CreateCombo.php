<?php

namespace App\Filament\Clusters\Product\Resources\Combos\Pages;

use App\Common\Constants\Product\StatusCombo;
use App\Filament\Clusters\Product\Resources\Combos\ComboResource;
use App\Services\ComboService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCombo extends CreateRecord
{
    protected static string $resource = ComboResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $data['status'] = StatusCombo::UPCOMING->value;
        return $data;
    }
}

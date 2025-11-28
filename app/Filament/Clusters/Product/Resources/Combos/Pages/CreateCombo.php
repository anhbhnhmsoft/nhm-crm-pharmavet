<?php

namespace App\Filament\Clusters\Product\Resources\Combos\Pages;

use App\Common\Constants\Product\StatusCombo;
use App\Filament\Clusters\Product\Resources\Combos\ComboResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCombo extends CreateRecord
{
    protected static string $resource = ComboResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()->id;
        $data['updated_by'] = Auth::user()->id;
        $data['organization_id'] = Auth::user()->organization_id;

        $data['status'] = StatusCombo::UPCOMING->value;
        return $data;
    }
}

<?php

namespace App\Filament\Clusters\Organization\Resources\Shifts\Pages;

use App\Filament\Clusters\Organization\Resources\Shifts\ShiftResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = Auth::user()->organization_id;

        return $data;
    }
}

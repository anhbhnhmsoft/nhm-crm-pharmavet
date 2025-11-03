<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\OrganizationService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['organization_id']) && $data['organization_id'] != $this->record->organization_id) {
            $service = app(OrganizationService::class);
            $result = $service->checkScalability($data['organization_id']);

            if ($result->isSuccess() && !$result->getData()['canDevelop']) {
                Notification::make()
                    ->title(__('filament.user.exceed_members_limit'))
                    ->danger()
                    ->send();

                throw ValidationException::withMessages([
                    'data.organization_id' => __('filament.user.exceed_members_limit'),
                ]);
            }
        }

        return $data;
    }
}

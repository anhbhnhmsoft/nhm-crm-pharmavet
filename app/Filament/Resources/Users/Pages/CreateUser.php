<?php

namespace App\Filament\Resources\Users\Pages;

use App\Common\Constants\User\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\OrganizationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function mount(): void
    {
        $user = Auth::user();
        if ($user->hasRole(UserRole::ADMIN)) {
            return;
        }
        /** @var OrganizationService $organizationService */
        $organizationService = app(OrganizationService::class);
        $result = $organizationService->checkScalability($user->organization_id);
        if ($result->isSuccess() && !$result->getData()['canDevelop']) {
            Notification::make()
                ->title(__('filament.user.exceed_members_limit'))
                ->danger();
        } else if ($result->isError() && $result->getMessage()) {
            Notification::make()
                ->title($result->getMessage())
                ->danger();
        }
        return;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (Auth::user()->hasRole(UserRole::ADMIN)) {
            $data['organization_id'] = Auth::user()->organization_id;
        }

        if (!empty($data['organization_id'])) {
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

<?php

namespace App\Filament\Clusters\Organization\Resources\Teams\Pages;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Organization\Resources\Teams\TeamResource;
use App\Services\UserService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['member_ids'] = $this->record->users()->pluck('id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
    {
        $memberIds = $this->data['member_ids'] ?? [];
        $userService = app(UserService::class);
        $result = $userService->updateTeamFoMember(users: $memberIds,teamId: $this->record->id,ableRemove: true);
        if ($result->isError()) {
            $errorMessage = $result->getMessage();
            $this->addError('data.member_ids', $errorMessage);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

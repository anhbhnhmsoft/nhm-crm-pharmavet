<?php

namespace App\Filament\Clusters\Organization\Resources\Teams\Pages;

use App\Common\Constants\User\UserPosition;
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
        $users = $this->record->users;
        $data['leader_ids'] = $users->where('position', UserPosition::LEADER->value)->pluck('id')->toArray();
        $data['staff_ids'] = $users->where('position', UserPosition::STAFF->value)->pluck('id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
    {
        $state = $this->form->getState();
        $leaderIds = $state['leader_ids'] ?? [];
        $staffIds = $state['staff_ids'] ?? [];
        $combinedIds = collect($leaderIds)->merge($staffIds)->filter()->unique()->values()->toArray();

        $userService = app(UserService::class);
        $result = $userService->updateTeamFoMember(users: $combinedIds, teamId: $this->record->id, ableRemove: true);
        if ($result->isError()) {
            $this->addError('data.staff_ids', $result->getMessage());
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

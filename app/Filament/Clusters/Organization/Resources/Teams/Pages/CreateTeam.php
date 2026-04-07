<?php

namespace App\Filament\Clusters\Organization\Resources\Teams\Pages;

use App\Filament\Clusters\Organization\Resources\Teams\TeamResource;
use App\Services\UserService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $state = $this->form->getState();
        $leaderIds = $state['leader_ids'] ?? [];
        $staffIds = $state['staff_ids'] ?? [];
        $combinedIds = collect($leaderIds)->merge($staffIds)->filter()->unique()->values()->toArray();

        $userService = app(UserService::class);
        $result = $userService->updateTeamFoMember(users: $combinedIds, teamId: $this->record->id, ableRemove: false);

        if ($result->isError()) {
            $this->addError('data.staff_ids', $result->getMessage());
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

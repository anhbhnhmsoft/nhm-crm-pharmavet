<?php

namespace App\Filament\Clusters\Organization\Resources\Teams\Pages;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Organization\Resources\Teams\TeamResource;
use App\Services\UserService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    public function mount(): void
    {
        parent::mount();

        $user = Auth::user();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $memberIds = $this->data['member_ids'] ?? [];
        $userService = app(UserService::class);
        $result = $userService->updateTeamFoMember(users: $memberIds,teamId: $this->record->id,ableRemove: false);
        if ($result->isError()) {
            $errorMessage = $result->getMessage();
            $this->addError('data.member_ids', $errorMessage) ;

        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

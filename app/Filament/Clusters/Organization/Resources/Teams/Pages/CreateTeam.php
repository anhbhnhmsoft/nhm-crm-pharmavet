<?php

namespace App\Filament\Clusters\Organization\Resources\Teams\Pages;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Organization\Resources\Teams\TeamResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    public function mount(): void
    {
        parent::mount();

        $user = Auth::user();

        // Nếu không phải SUPER_ADMIN, tự động set organization_id
        if (!$user->hasRole(UserRole::SUPER_ADMIN)) {
            $this->form->fill([
                'organization_id' => $user->organization_id,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Đảm bảo organization_id luôn có giá trị
        if (empty($data['organization_id'])) {
            $data['organization_id'] = Auth::user()->organization_id;
        }

        // Tự động set created_by
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Gán users vào team sau khi tạo
        $memberIds = $this->data['member_ids'] ?? [];

        if (!empty($memberIds)) {
            \App\Models\User::whereIn('id', $memberIds)
                ->update(['team_id' => $this->record->id]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

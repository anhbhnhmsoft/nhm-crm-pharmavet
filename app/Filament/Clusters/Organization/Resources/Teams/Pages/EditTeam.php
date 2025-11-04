<?php

namespace App\Filament\Clusters\Organization\Resources\Teams\Pages;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Organization\Resources\Teams\TeamResource;
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
        // Load member IDs vào form
        $data['member_ids'] = $this->record->users()->pluck('id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Tự động set updated_by
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
    {
        // Sync team members
        $memberIds = $this->data['member_ids'] ?? [];

        // Cập nhật team_id cho users
        // Remove team_id của users không còn trong danh sách
        \App\Models\User::where('team_id', $this->record->id)
            ->whereNotIn('id', $memberIds)
            ->update(['team_id' => null]);

        // Set team_id cho users được chọn
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

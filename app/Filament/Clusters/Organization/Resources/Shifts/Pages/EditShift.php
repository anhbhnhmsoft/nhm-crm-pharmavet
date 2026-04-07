<?php

namespace App\Filament\Clusters\Organization\Resources\Shifts\Pages;

use App\Filament\Clusters\Organization\Resources\Shifts\ShiftResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditShift extends EditRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function ($record, DeleteAction $action) {
                    if ($record->users()->count() > 0) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title(__('filament.shift.notifications.delete_failed.title'))
                            ->body(__('filament.shift.notifications.delete_failed.body'))
                            ->send();
                        $action->cancel();
                    }
                }),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

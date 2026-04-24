<?php

namespace App\Filament\Clusters\Organization\Resources\Shifts\Pages;

use App\Filament\Clusters\Organization\Resources\Shifts\ShiftResource;
use App\Services\ShiftService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditShift extends EditRecord
{
    protected static string $resource = ShiftResource::class;

    protected function beforeSave(): void
    {
        $this->validateShiftSchedule();
    }

    protected function afterSave(): void
    {
        $userIds = $this->form->getRawState()['users'] ?? [];

        $this->record->users()->sync($userIds);
    }

    protected function validateShiftSchedule(): void
    {
        $data = $this->form->getRawState();
        $organizationId = (int) ($data['organization_id'] ?? $this->record?->organization_id ?? Auth::user()->organization_id);
        $startTime = $this->normalizeTime($data['start_time'] ?? null);
        $endTime = $this->normalizeTime($data['end_time'] ?? null);

        if (! $startTime || ! $endTime) {
            return;
        }

        if ($startTime === $endTime) {
            throw ValidationException::withMessages([
                'end_time' => __('filament.shift.validation.start_equals_end'),
            ]);
        }

        if ($endTime < $startTime) {
            throw ValidationException::withMessages([
                'end_time' => __('filament.shift.validation.end_before_start'),
            ]);
        }

        /** @var ShiftService $shiftService */
        $shiftService = app(ShiftService::class);

        if ($shiftService->isOverlap($organizationId, $startTime, $endTime, $this->record?->id)) {
            throw ValidationException::withMessages([
                'end_time' => __('filament.shift.validation.overlap'),
            ]);
        }
    }

    protected function normalizeTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function ($record, DeleteAction $action) {
                    if ($record->hasAssignedUsers()) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title(__('filament.shift.notifications.delete_failed.title'))
                            ->body(__('filament.shift.notifications.delete_failed.body'))
                            ->send();
                        $action->cancel();
                    }
                }),
            ForceDeleteAction::make()
                ->before(function ($record, ForceDeleteAction $action) {
                    if ($record->hasAssignedUsers()) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title(__('filament.shift.notifications.delete_failed.title'))
                            ->body(__('filament.shift.notifications.delete_failed.body'))
                            ->send();
                        $action->cancel();
                    }
                }),
            RestoreAction::make(),
        ];
    }
}

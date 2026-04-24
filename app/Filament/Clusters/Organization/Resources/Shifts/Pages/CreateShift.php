<?php

namespace App\Filament\Clusters\Organization\Resources\Shifts\Pages;

use App\Filament\Clusters\Organization\Resources\Shifts\ShiftResource;
use App\Services\ShiftService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;

    protected function beforeCreate(): void
    {
        $this->validateShiftSchedule();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = $data['organization_id'] ?? Auth::user()->organization_id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $userIds = $this->form->getRawState()['users'] ?? [];

        $this->record->users()->sync($userIds);
    }

    protected function validateShiftSchedule(): void
    {
        $data = $this->form->getRawState();
        $organizationId = (int) ($data['organization_id'] ?? Auth::user()->organization_id);
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

        if ($shiftService->isOverlap($organizationId, $startTime, $endTime)) {
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

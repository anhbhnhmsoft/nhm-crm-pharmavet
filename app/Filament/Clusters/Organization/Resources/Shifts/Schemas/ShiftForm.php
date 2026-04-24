<?php

namespace App\Filament\Clusters\Organization\Resources\Shifts\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Common\Constants\User\UserRole;
use App\Models\Shift;
use App\Services\ShiftService;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        $authUser = Auth::user();
        $userOrganizationId = $authUser?->organization_id;

        return $schema
            ->schema([
                Section::make(__('filament.shift.sections.basic_info'))
                    ->description(__('filament.shift.sections.basic_info_description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('common.table.name'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_length', ['min' => 3]),
                                'max' => __('common.error.max_length', ['max' => 255]),
                            ]),

                        Hidden::make('organization_id')
                            ->default($userOrganizationId)
                            ->dehydrated(),

                        TimePicker::make('start_time')
                            ->label(__('filament.shift.table.start_time'))
                            ->native(false)
                            ->seconds(false)
                            ->hoursStep(1)
                            ->minutesStep(5)
                            ->timezone('Asia/Ho_Chi_Minh')
                            ->format('H:i:s')
                            ->displayFormat('H:i')
                            ->required()
                            ->live()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('filament.shift.table.start_time'),
                                ]),
                                'date' => __('validation.date', [
                                    'attribute' => __('filament.shift.table.start_time'),
                                ]),
                            ]),

                        TimePicker::make('end_time')
                            ->label(__('filament.shift.table.end_time'))
                            ->native(false)
                            ->seconds(false)
                            ->hoursStep(1)
                            ->minutesStep(5)
                            ->timezone('Asia/Ho_Chi_Minh')
                            ->format('H:i:s')
                            ->displayFormat('H:i')
                            ->required()
                            ->live()
                            ->extraInputAttributes(['required' => false])
                            ->rules([
                                fn(Get $get, ?Shift $record, ShiftService $shiftService) => function (string $attribute, $value, \Closure $fail) use ($get, $record, $shiftService) {
                                    $startTime = (string) ($get('start_time') ?? '');
                                    $endTime = (string) ($value ?? '');

                                    if ($startTime === '' || $endTime === '') {
                                        return;
                                    }

                                    $start = strtotime($startTime);
                                    $end = strtotime($endTime);

                                    if ($start === false || $end === false) {
                                        return;
                                    }

                                    if ($startTime === $endTime) {
                                        $fail(__('filament.shift.validation.start_equals_end'));
                                        return;
                                    }

                                    if ($end < $start) {
                                        $fail(__('filament.shift.validation.end_before_start'));
                                        return;
                                    }

                                    $organizationId = (int) ($get('organization_id') ?: Auth::user()->organization_id);
                                    
                                    $startTimeStr = date('H:i:s', $start);
                                    $endTimeStr = date('H:i:s', $end);

                                    $overlap = $shiftService->isOverlap(
                                        $organizationId,
                                        $startTimeStr,
                                        $endTimeStr,
                                        $record?->id
                                    );

                                    if ($overlap) {
                                        $fail(__('filament.shift.validation.overlap'));
                                    }
                                }
                            ])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('filament.shift.table.end_time'),
                                ]),
                                'date' => __('validation.date', [
                                    'attribute' => __('filament.shift.table.end_time'),
                                ]),
                            ]),
                    ]),

                Section::make(__('filament.shift.sections.user_assignment'))
                    ->description(__('filament.shift.sections.user_assignment_description'))
                    ->schema([
                        Select::make('users')
                            ->label(__('filament.shift.form.assign_users'))
                            ->relationship(
                                name: 'users',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query, Get $get) => $query
                                    ->where('organization_id', (int) ($get('organization_id') ?: $userOrganizationId))
                                    ->where('disable', false)
                                    ->whereNotIn('role', [UserRole::SUPER_ADMIN->value])
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->extraInputAttributes(['required' => false])
                            ->placeholder(__('filament.shift.placeholder.select_users')),
                    ]),
            ]);
    }
}

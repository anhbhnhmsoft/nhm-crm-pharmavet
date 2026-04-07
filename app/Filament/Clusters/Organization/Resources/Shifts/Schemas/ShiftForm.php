<?php

namespace App\Filament\Clusters\Organization\Resources\Shifts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Common\Constants\User\UserRole;
use App\Utils\Helper;
use App\Models\Shift;
use App\Services\ShiftService;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        $authUser = Auth::user();
        $isSuperAdmin = Helper::checkPermission([UserRole::SUPER_ADMIN->value], $authUser->role);
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

                        Select::make('organization_id')
                            ->label(__('.organization.label'))
                            ->relationship(
                                name: 'organization',
                                titleAttribute: 'name',
                            )
                            ->default($userOrganizationId)
                            ->required()
                            ->preload()
                            ->hidden(!$isSuperAdmin)
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        TextInput::make('start_time')
                            ->label(__('filament.shift.table.start_time'))
                            ->placeholder('08:00')
                            ->mask('99:99')
                            ->prefixIcon('heroicon-m-clock')
                            ->required()
                            ->live()
                            ->regex('/^([01][0-9]|2[0-3]):[0-5][0-9]$/')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'regex' => __('common.error.time_format'),
                            ]),

                        TextInput::make('end_time')
                            ->label(__('filament.shift.table.end_time'))
                            ->placeholder('17:00')
                            ->mask('99:99')
                            ->prefixIcon('heroicon-m-clock')
                            ->required()
                            ->regex('/^([01][0-9]|2[0-3]):[0-5][0-9]$/')
                            ->extraInputAttributes(['required' => false])
                            ->rules([
                                fn(Get $get, ?Shift $record, ShiftService $shiftService) => function (string $attribute, $value, \Closure $fail) use ($get, $record, $shiftService) {
                                    $startTime = $get('start_time');

                                    if (!$startTime || !$value) {
                                        return;
                                    }

                                    if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $startTime) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
                                        return;
                                    }

                                    if ($startTime === $value) {
                                        $fail(__('filament.shift.validation.start_equals_end'));
                                        return;
                                    }

                                    $start = strtotime($startTime);
                                    $end = strtotime($value);

                                    if ($end < $start) {
                                        $fail(__('filament.shift.validation.end_before_start'));
                                        return;
                                    }

                                    $organizationId = $get('organization_id') ?? Auth::user()->organization_id;
                                    
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
                                'required' => __('common.error.required'),
                                'regex' => __('common.error.time_format'),
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
                                    ->where('organization_id', $get('organization_id'))
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

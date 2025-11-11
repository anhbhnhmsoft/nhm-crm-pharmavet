<?php

namespace App\Filament\Clusters\Organization\Resources\Shifts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Common\Constants\User\UserRole;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        $userOrganizationId = Auth::user()?->organization_id;
        $isAdmin = Auth::user()?->hasRole(UserRole::SUPER_ADMIN) ?? false;

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
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_length', ['min' => 3]),
                                'max' => __('common.error.max_length', ['max' => 255]),
                            ]),

                        Select::make('organization_id')
                            ->label(__('filament.organization.label'))
                            ->relationship(
                                name: 'organization',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query) => $query->when(
                                    $userOrganizationId,
                                    fn($query) => $query->where('id', $userOrganizationId)
                                )
                            )
                            ->default($userOrganizationId)
                            ->required()
                            ->preload()
                            ->disabled()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        TimePicker::make('start_time')
                            ->label(__('filament.shift.table.start_time'))
                            ->required()
                            ->seconds(false)
                            ->displayFormat('H:i')
                            ->live()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        TimePicker::make('end_time')
                            ->label(__('filament.shift.table.end_time'))
                            ->required()
                            ->seconds(false)
                            ->displayFormat('H:i')
                            ->rules([
                                fn(Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $startTime = $get('start_time');

                                    if (!$startTime || !$value) {
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
                                    }
                                }
                            ])
                            ->validationMessages([
                                'required' => __('common.error.required'),
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
                            ->required()
                            ->placeholder(__('filament.shift.placeholder.select_users'))
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),
                    ]),
            ]);
    }
}

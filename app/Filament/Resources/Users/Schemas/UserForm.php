<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Common\Constants\User\UserRole;
use App\Common\Constants\User\UserPosition;
use App\Models\Organization;
use App\Models\Team;
use Closure; // Import Closure
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $authUser = Auth::user();
        $isSuperAdmin = $authUser->hasRole(UserRole::SUPER_ADMIN);

        return $schema
            ->components([
                Section::make(__('filament.user.basic_info'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.user.name'))
                            ->required()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max'      => __('common.error.max_length', ['max' => 255])
                            ]),

                        TextInput::make('username')
                            ->label(__('filament.user.username'))
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max'      => __('common.error.max_length', ['max' => 50]),
                                'unique'   => __('common.error.unique')
                            ]),

                        TextInput::make('email')
                            ->label(__('filament.user.email'))
                            ->email()
                            // Thêm lại Unique cho email
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->validationMessages([
                                'email'     => __('common.error.email'),
                                'required'  => __('common.error.required'),
                                'unique'    => __('common.error.unique')
                            ]),
                        TextInput::make('password')
                            ->label(__('filament.user.password'))
                            ->password()
                            ->required(fn($livewire) => $livewire instanceof CreateRecord)
                            ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn($state) => filled($state))
                            ->revealable()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max'      => __('common.error.max_length', ['max' => 255])
                            ]),
                    ])
                    ->columns(2),

                Section::make(__('filament.user.account_info'))
                    ->schema([

                        Select::make('role')
                            ->label(__('filament.user.role'))
                            ->options(UserRole::getOptions())
                            ->required()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('position')
                            ->label(__('filament.user.position'))
                            ->options(UserPosition::getOptions())
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('organization_id')
                            ->label(__('filament.user.organization'))
                            ->options(Organization::where('disable', false)->pluck('name', 'id'))
                            ->searchable()
                            ->default(fn() => $isSuperAdmin ? null : $authUser->organization_id)
                            ->disabled(fn() => !$isSuperAdmin)
                            ->preload()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // reset team_id khi đổi organization
                                $set('team_id', null);
                            }),

                        Select::make('team_id')
                            ->label(__('filament.user.team'))
                            ->options(fn(Get $get) => Team::query()
                                ->where('organization_id', $get('organization_id'))
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Toggle::make('disable')
                            ->label(__('filament.user.disable'))
                            ->default(false)
                            ->hidden(fn($livewire) => $livewire instanceof CreateRecord),
                    ])
                    ->columns(2),

                Section::make(__('filament.user.other_info'))
                    ->schema([
                        TextInput::make('salary')
                            ->label(__('filament.user.salary'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000000000)
                            ->required()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min'      => __('common.error.min_value', ['min' => 0]),
                                'max'      => __('common.error.max_value', ['max' => 1000000000]),
                            ]),

                        TextInput::make('phone')
                            ->label(__('filament.user.phone'))
                            ->tel()
                            ->maxLength(20)
                            ->numeric()
                            ->validationMessages([
                                'max'      => __('common.error.max_length', ['max' => 20]),
                                'numeric'  => __('common.error.numeric')
                            ]),
                        TextInput::make('online_hours')
                            ->label(__('filament.user.online_hours'))
                            ->numeric()
                            ->disabled()
                            ->hidden(fn($livewire) => $livewire instanceof CreateRecord),

                        DateTimePicker::make('last_login_at')
                            ->label(__('filament.user.last_login'))
                            ->disabled()
                            ->hidden(fn($livewire) => $livewire instanceof CreateRecord),

                        DateTimePicker::make('last_logout_at')
                            ->label(__('filament.user.last_logout'))
                            ->disabled()
                            ->hidden(fn($livewire) => $livewire instanceof CreateRecord),
                    ])
                    ->columns(2),
            ]);
    }
}

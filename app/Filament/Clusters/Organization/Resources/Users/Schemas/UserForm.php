<?php

namespace App\Filament\Clusters\Organization\Resources\Users\Schemas;

use App\Common\Constants\GateKey;
use App\Common\Constants\Team\TeamType;
use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $authUser = Auth::user();
        return $schema
            ->components([
                Section::make(__('filament.user.basic_info'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('user.form.name'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max' => __('common.error.max_length', ['max' => 255])
                            ]),

                        TextInput::make('username')
                            ->label(__('user.form.username'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max' => __('common.error.max_length', ['max' => 50]),
                                'unique' => __('common.error.unique')
                            ]),

                        TextInput::make('email')
                            ->label(__('user.form.email'))
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->extraInputAttributes(['required' => false,'type' => 'text'])

                            ->maxLength(255)
                            ->validationMessages([
                                'email' => __('common.error.email'),
                                'required' => __('common.error.required'),
                                'unique' => __('common.error.unique')
                            ]),
                        TextInput::make('password')
                            ->label(__('user.form.password'))
                            ->helperText(__('user.form.password_helper'))
                            ->password()
                            ->extraInputAttributes(['required' => false])
                            ->required(fn($livewire) => $livewire instanceof CreateRecord)
                            ->rules([
                                'required',
                                'max:20',
                                'min:8',
                                'regex:/[a-z]/',      // ít nhất 1 chữ thường
                                'regex:/[A-Z]/',      // ít nhất 1 chữ hoa
                                'regex:/[0-9]/',      // ít nhất 1 số
                                'regex:/[@$!%*#?&]/', // ít nhất 1 ký tự đặc biệt
                            ])
                            ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn($state) => filled($state))
                            ->revealable()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_length', ['min' => 8]),
                                'max' => __('common.error.max_length', ['max' => 20]),
                                'regex' => __('common.error.password')
                            ])
                    ])
                    ->columns(2),

                Section::make(__('user.form.account_info'))
                    ->schema([
                        Select::make('organization_id')
                            ->label(__('user.form.organization'))
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->extraInputAttributes(['required' => false])
                            ->preload()
                            ->visible(function () {
                                return Gate::allows(GateKey::IS_SUPER_ADMIN);
                            })
                            ->live()
                            ->required(function () {
                                return Gate::allows(GateKey::IS_SUPER_ADMIN);
                            }),

                        Select::make('role')
                            ->label(__('user.form.role'))
                            ->searchable()
                            ->extraInputAttributes(['required' => false])
                            ->options(UserRole::getOptions())
                            ->required()
                            ->live()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('position')
                            ->label(__('user.form.position'))
                            ->searchable()
                            ->extraInputAttributes(['required' => false])
                            ->options(UserPosition::getOptions())
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('team_id')
                            ->label(__('filament.user.team'))
                            ->relationship(
                                'teams',
                                'name',
                                function (Builder $query, callable $get) use ($authUser) {

                                    $orgId = Gate::allows(GateKey::IS_SUPER_ADMIN) ? $get('organization_id') : $authUser->organization_id;
                                    $query->where('organization_id', $orgId);

                                    $role = $get('role');
                                    if ($role) {
                                        $teamTypes = match ((int) $role) {
                                            UserRole::SALE->value => [TeamType::SALE->value, TeamType::CSKH->value],
                                            UserRole::MARKETING->value => [TeamType::MARKETING->value],
                                            UserRole::WAREHOUSE->value => [TeamType::BILL_OF_LADING->value],
                                            default => [],
                                        };

                                        if (!empty($teamTypes)) {
                                            $query->whereIn('type', $teamTypes);
                                        }
                                    }

                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->hidden(fn( $get) => (int) $get('role') === UserRole::ADMIN->value)
                            ->live(),

                        Toggle::make('disable')
                            ->label(__('filament.user.disable'))
                            ->default(false)
                            ->hidden(fn($livewire) => $livewire instanceof CreateRecord),
                    ])
                    ->columns(2),

                Section::make(__('filament.user.other_info'))
                    ->schema([
                        TextInput::make('salary')
                            ->label(__('user.form.salary'))
                            ->numeric()
                            ->extraInputAttributes(['required' => false])
                            ->minValue(0)
                            ->maxValue(1000000000)
                            ->required()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_value', ['min' => 0]),
                                'max' => __('common.error.max_value', ['max' => 1000000000]),
                            ]),

                        TextInput::make('phone')
                            ->label(__('user.form.phone'))
                            ->placeholder(__('user.form.phone_placeholder'))
                            ->maxLength(15)
                            ->unique(ignoreRecord: true)
                            ->rules([
                                'regex:/^(0|(\+84))[3|5|7|8|9][0-9]{8}$/',
                            ])
                            ->validationMessages([
                                'regex' => __('common.error.phone_invalid'), // VD: Số điện thoại không đúng định dạng VN.
                                'max'   => __('common.error.max_length', ['max' => 15]),
                            ]),

                        TextInput::make('online_hours')
                            ->label(__('user.form.online_hours'))
                            ->numeric()
                            ->disabled()
                            ->hidden(fn($livewire) => $livewire instanceof CreateRecord),

                        DateTimePicker::make('last_login_at')
                            ->label(__('user.form.last_login'))
                            ->disabled()
                            ->hidden(fn($livewire) => $livewire instanceof CreateRecord),

                        DateTimePicker::make('last_logout_at')
                            ->label(__('user.form.last_logout'))
                            ->disabled()
                            ->hidden(fn($livewire) => $livewire instanceof CreateRecord),
                    ])
                    ->columns(2),
            ]);
    }
}

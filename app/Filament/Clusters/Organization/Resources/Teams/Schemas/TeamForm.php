<?php

namespace App\Filament\Clusters\Organization\Resources\Teams\Schemas;

use App\Common\Constants\Team\TeamType;
use App\Common\Constants\User\UserRole;
use App\Services\UserService;
use App\Utils\Helper;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Common\Constants\User\UserPosition;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        $authUser = Auth::user();
        $isSuperAdmin = Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
        ], $authUser->role);
        $userService = app(UserService::class);
        return $schema
            ->components([
                Section::make(__('filament.team.general_info'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.team.name'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->extraInputAttributes(['required' => false])
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $slug = Str::slug($state, '-');
                                    $set('code', Str::upper($slug));
                                }
                            })
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_length', ['min' => 3]),
                                'max' => __('common.error.max_length', ['max' => 255])
                            ]),

                        TextInput::make('code')
                            ->label(__('filament.team.code'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->reactive()
                            ->debounce(1000)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $slug = Str::slug($state, '-');
                                    $set('code', Str::upper($slug));
                                }
                            })
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_length', ['min' => 3]),
                                'max' => __('common.error.max_length', ['max' => 20]),
                                'unique' => __('common.error.unique'),
                            ]),

                        $isSuperAdmin 
                            ? Select::make('organization_id')
                                ->label(__('filament.team.organization'))
                                ->relationship('organization', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->extraInputAttributes(['required' => false])
                                ->validationMessages([
                                    'required' => __('common.error.required'),
                                ])
                            : Hidden::make('organization_id')
                                ->default($authUser->organization_id),

                        Select::make('type')
                            ->label(__('filament.team.type'))
                            ->required()
                            ->live()
                            ->options(TeamType::getOptions())
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Textarea::make('description')
                            ->label(__('filament.team.description'))
                            ->maxLength(1000)
                            ->rows(4)
                            ->columnSpanFull()
                            ->validationMessages([
                                'max' => __('common.error.max_length', ['max' => 1000])
                            ]),
                    ])
                    ->columns(['default' => 2])
                    ->columnSpan(['default' => 1]),

                Section::make(__('filament.team.members'))
                    ->schema([
                        Select::make('leader_ids')
                            ->label(__('filament.team.leader'))
                            ->multiple()
                            ->maxItems(1)
                            ->searchable()
                            ->options(fn(Get $get, $record) => self::getMemberOptions($get, UserPosition::LEADER->value, $record))
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'in' => __('filament.team.error.member_not_match_type'),
                            ])
                            ->columnSpanFull(),

                        Select::make('staff_ids')
                            ->label(__('filament.team.team_members'))
                            ->multiple()
                            ->searchable()
                            ->options(fn(Get $get, $record) => self::getMemberOptions($get, UserPosition::STAFF->value, $record))
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'in' => __('filament.team.error.member_not_match_type'),
                            ])
                            ->columnSpanFull(),

                        Placeholder::make('member_count')
                            ->label(__('filament.team.total_members'))
                            ->content(function ($get, $record) {
                                $leaderIds = $get('leader_ids');
                                $staffIds = $get('staff_ids');
                                $count = collect($leaderIds)->merge($staffIds)->filter()->unique()->count();

                                return $count . ' ' . __('filament.team.members');
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(['default' => 1])
                    ->collapsible(),
            ])
            ->columns(['default' => 2]);
    }

    protected static function getRoleByTeamType($teamType): ?int
    {
        return match (true) {
            in_array($teamType, [TeamType::SALE->value, TeamType::CSKH->value]) => UserRole::SALE->value,
            $teamType === TeamType::MARKETING->value => UserRole::MARKETING->value,
            $teamType === TeamType::BILL_OF_LADING->value => UserRole::WAREHOUSE->value,
            default => null,
        };
    }

    protected static function applyRoleFilter(Builder $query, $teamType): void
    {
        if ($role = self::getRoleByTeamType($teamType)) {
            $query->where('role', $role);
        }
    }

    protected static function applyRoleFilterToParams(array &$params, $teamType): void
    {
        if ($role = self::getRoleByTeamType($teamType)) {
            $params['role'] = $role;
        }
    }

    protected static function getMemberOptions(Get $get, int $position, $record = null): array
    {
        $authUser = Auth::user();
        $isSuperAdmin = Helper::checkPermission([UserRole::SUPER_ADMIN->value], $authUser->role);
        $userService = app(UserService::class);

        $orgId = $isSuperAdmin ? $get('organization_id') : $authUser->organization_id;
        if (!$orgId) return [];

        $teamType = $get('type');
        if (!$teamType) return [];

        $params = [
            'organization_id' => $orgId,
            'position' => $position,
            'disable' => false,
        ];

        if ($position === UserPosition::STAFF->value) {
            $params['available_for_team'] = $record?->id;
        }

        self::applyRoleFilterToParams($params, $teamType);

        $result = $userService->getListUser($params);
        return $result->isSuccess() ? $result->getData()->pluck('name', 'id')->toArray() : [];
    }
}

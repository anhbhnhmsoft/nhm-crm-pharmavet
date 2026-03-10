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
use Illuminate\Support\Str;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

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
                            ->live(debounce: 1000)
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
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->reactive()
                            ->debounce(1000)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $slug = Str::slug($state, '-');
                                    $set('code', Str::upper($slug));
                                }
                            })
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_length', ['min' => 3]),
                                'max' => __('common.error.max_length', ['max' => 255]),
                                'unique' => __('common.error.unique'),
                            ]),

                        Select::make('organization_id')
                            ->label(__('filament.team.organization'))
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn() => $isSuperAdmin ? null : $authUser->organization_id)
                            ->disabled(fn() => !$isSuperAdmin)
                            ->dehydrated(true)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('type')
                            ->label(__('filament.team.type'))
                            ->required()
                            ->options(TeamType::getOptions())
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
                    ->columns(2)
                    ->columnSpan(1),

                Section::make(__('filament.team.members'))
                    ->schema([
                        Select::make('member_ids')
                            ->label(__('filament.team.team_members'))
                            ->live()
                            ->relationship(
                                name: 'users',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, Get $get) use ($authUser, $isSuperAdmin) {
                                    $orgId = $isSuperAdmin ? $get('organization_id') : $authUser->organization_id;

                                    if (!$orgId) {
                                        return $query->whereRaw('1=0');
                                    }

                                    $query->where('organization_id', $orgId)
                                        ->where('disable', false)
                                        ->whereNotIn('role', [UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value]);

                                    if ($isSuperAdmin) {
                                        return $query;
                                    }

                                    switch ($get('type')) {
                                        case TeamType::SALE->value:
                                            return $query->where('role', UserRole::SALE->value);
                                        case TeamType::CSKH->value:
                                            return $query->where('role', UserRole::SALE->value);
                                        case TeamType::MARKETING->value:
                                            return $query->where('role', UserRole::MARKETING->value);
                                        case TeamType::BILL_OF_LADING->value:
                                            return $query->where('role', UserRole::WAREHOUSE->value);
                                        default:
                                            return $query;
                                    }
                                }
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search, Get $get) use ($authUser, $isSuperAdmin, $userService) {
                                $orgId = $isSuperAdmin ? $get('organization_id') : $authUser->organization_id;
                                if (!$orgId)
                                    return [];

                                $params = [
                                    'keyword' => $search,
                                    'organization_id' => $orgId,
                                    'disable' => false,
                                ];

                                switch ($get('type')) {
                                    case TeamType::SALE->value:
                                        $params['role'] = UserRole::SALE->value;
                                        break;
                                    case TeamType::CSKH->value:
                                        $params['role'] = UserRole::SALE->value;
                                        break;
                                    case TeamType::MARKETING->value:
                                        $params['role'] = UserRole::MARKETING->value;
                                        break;
                                    case TeamType::BILL_OF_LADING->value:
                                        $params['role'] = UserRole::WAREHOUSE->value;
                                        break;
                                }

                                $result = $userService->getListUser($params);

                                if ($result && $result->isSuccess()) {
                                    return $result->getData()
                                        ->whereNotIn('role', [UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value])
                                        ->take(50)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                }
                                return [];
                            })
                            ->getOptionLabelUsing(function ($value) use ($userService): ?string {
                                $result = $userService->find($value);
                                return $result->isSuccess() ? $result->getData()->name : null;
                            })
                            ->columnSpanFull(),

                        Placeholder::make('member_count')
                            ->label(__('filament.team.total_members'))
                            ->content(function ($get, $record) {
                                if ($record) {
                                    return $record->users()->count() . ' ' . __('filament.team.members');
                                }

                                $memberIds = $get('member_ids');
                                $count = collect($memberIds)->filter()->count();

                                return $count . ' ' . __('filament.team.members');
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1)
                    ->collapsible(),
            ])
            ->columns(2);
    }
}

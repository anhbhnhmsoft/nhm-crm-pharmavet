<?php

namespace App\Filament\Clusters\Organization\Resources\Teams\Schemas;

use App\Common\Constants\Team\TeamType;
use App\Common\Constants\User\UserRole;
use App\Services\UserService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class TeamForm
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public static function configure(Schema $schema): Schema
    {
        $authUser = Auth::user();
        $isSuperAdmin = $authUser->hasRole(UserRole::SUPER_ADMIN);
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
                                'min'      => __('common.error.min_length', ['min' => 3]),
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
                                'min'      => __('common.error.min_length', ['min' => 3]),
                                'max'      => __('common.error.max_length', ['max' => 255]),
                                'unique'   => __('common.error.unique'),
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
                            ->relationship(
                                name: 'users',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query) use ($authUser, $isSuperAdmin) {
                                    if ($isSuperAdmin) {
                                        return $query
                                            ->whereNotNull('organization_id')
                                            ->where('disable', false)
                                        ;
                                    }
                                    return $query
                                        ->where('organization_id', $authUser->organization_id)
                                        ->whereNotIn('role', [UserRole::SUPER_ADMIN->value])
                                        ->where('disable', false);
                                }
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) use ($userService) {
                                $result = $userService->getListUser([
                                    'keyword' => $search
                                ]);
                                if ($result->isSuccess() && $search) {
                                    return $result->getData()
                                        ->whereNotIn('role', [UserRole::SUPER_ADMIN->value])
                                        ->limit(50)
                                        ->pluck('name', 'id');
                                } else {
                                    return [];
                                }
                            })
                            ->getOptionLabelUsing(function ($value, $userService): ?string {
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
                                return is_array($memberIds) ? count($memberIds) . ' ' . __('filament.team.members') : '0 ' . __('filament.team.members');
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1)
                    ->collapsible(),
            ])
            ->columns(2);
    }
}

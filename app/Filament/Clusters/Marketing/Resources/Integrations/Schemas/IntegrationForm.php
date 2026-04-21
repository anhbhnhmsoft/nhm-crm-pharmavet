<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations\Schemas;

use App\Common\Constants\Marketing\FacebookConnectionStatus;
use App\Common\Constants\Marketing\IntegrationStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\Marketing\IntegrationEntityType;
use App\Models\Integration;
use App\Models\Product;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IntegrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament.integration.sections.basic_info.title'))
                    ->description(__('filament.integration.sections.basic_info.description'))
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('type')
                                    ->label(__('filament.integration.fields.type'))
                                    ->options(IntegrationType::toOptions())
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->disabled(fn(?Integration $record) => $record !== null)
                                    ->dehydrated()
                                    ->afterStateUpdated(function ($state, Set $set, ?Integration $record) {
                                        // Chỉ set default khi đang tạo mới (không có $record)
                                        if ($record) {
                                            return;
                                        }

                                        $type = IntegrationType::tryFrom($state);

                                        if ($type === IntegrationType::WEBSITE) {
                                            $set('config.webhook_secret', fn($current) => $current ?: Str::random(32));
                                            $set('config.site_id', fn($current) => $current ?: Str::lower(Str::random(16)));
                                        }
                                    })
                                    ->helperText(fn(Get $get) => IntegrationType::tryFrom($get('type'))?->description())
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('name')
                                    ->label(__('filament.integration.fields.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder(__('filament.integration.fields.name_placeholder'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'max' => __('common.error.max_length', ['max' => 255]),
                                    ]),

                                Select::make('config.input_mode')
                                    ->label(__('filament.integration.fields.input_mode'))
                                    ->options([
                                        'auto' => __('filament.integration.fields.input_mode_auto'),
                                        'manual' => __('filament.integration.fields.input_mode_manual'),
                                    ])
                                    ->default('auto')
                                    ->native(false),

                                Select::make('config.distribution_mode')
                                    ->label(__('filament.integration.fields.distribution_mode'))
                                    ->options([
                                        'manual_pick' => __('filament.integration.fields.distribution_mode_manual_pick'),
                                        'quota' => __('filament.integration.fields.distribution_mode_quota'),
                                        'round_robin' => __('filament.integration.fields.distribution_mode_round_robin'),
                                    ])
                                    ->default('manual_pick')
                                    ->native(false),

                                TextInput::make('config.distribution_limit')
                                    ->label(__('filament.integration.fields.distribution_limit'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->helperText(__('filament.integration.fields.distribution_limit_helper')),
                            ]),
                    ]),

                Section::make(__('filament.integration.sections.facebook_login.title'))
                    ->description(__('filament.integration.sections.facebook_login.description'))
                    ->schema([
                        Placeholder::make('facebook_status')
                            ->label(false)
                            ->content(function (?Integration $record) {
                                if (!$record) {
                                    return __('filament.integration.sections.facebook_connect_required');
                                }

                                $approvedPages = $record->approvedFacebookPages()->count();
                                $pendingPages = $record->pendingFacebookPages()->count();
                                $lastSync = $record->last_sync_at ? $record->last_sync_at->diffForHumans() : __('filament.integration.sections.never_synced');

                                if ($approvedPages > 0) {
                                    return __('filament.integration.sections.facebook_approval_summary', [
                                        'approved' => $approvedPages,
                                        'pending' => $pendingPages,
                                        'last_sync' => $lastSync,
                                    ]);
                                }

                                if ($pendingPages > 0) {
                                    return __('filament.integration.sections.facebook_pending_summary', [
                                        'count' => $pendingPages,
                                        'last_sync' => $lastSync,
                                    ]);
                                }

                                return __('filament.integration.sections.facebook_connect_required');
                            }),

                        View::make('filament.components.facebook-oauth-button')
                            ->viewData(function (?Integration $record) {
                                $apiToken = null;

                                if (Auth::user() && config('jwt.secret')) {
                                    try {
                                        $apiToken = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser(Auth::user());
                                    } catch (\Throwable) {
                                        $apiToken = null;
                                    }
                                }

                                return [
                                    'record' => $record ?? 'temp',
                                    'isConnected' => (bool) ($record && $record->status === IntegrationStatus::CONNECTED->value),
                                    'pagesCount' => $record ? $record->approvedFacebookPages()->count() : 0,
                                    'pendingPagesCount' => $record ? $record->pendingFacebookPages()->count() : 0,
                                    'lastSync' => $record ? ($record->last_sync_at ? $record->last_sync_at->diffForHumans() : now()) : now(),
                                    'status' => $record?->status,
                                    'statusMessage' => $record?->status_message,
                                    'apiToken' => $apiToken,
                                    'facebookAppId' => (string) config('services.facebook.app_id', ''),
                                ];
                            })
                            ->columnSpanFull(),

                        Repeater::make('entities')
                            ->label(__('filament.integration.fields.connected_pages'))
                            ->relationship('entities', modifyQueryUsing: fn($query) => $query->where('type', IntegrationEntityType::PAGE_META->value))
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        View::make('filament.components.avatar-placeholder')
                                            ->viewData(fn(array $state) => [
                                                'url' => $state['metadata']['picture'] ?? null,
                                                'alt' => $state['name'] ?? 'Page Avatar',
                                            ])
                                            ->columnSpan(1),

                                        TextInput::make('name')
                                            ->label(__('filament.integration.fields.page_name'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(3),

                                        TextInput::make('external_id')
                                            ->label(__('filament.integration.fields.page_id'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(3),

                                        Select::make('metadata.default_product_id')
                                            ->label(__('filament.integration.fields.default_product'))
                                            ->options(fn() => Product::where('organization_id', Auth::user()->organization_id)->pluck('name', 'id'))
                                            ->searchable()
                                            ->nullable()
                                            ->native(false)
                                            ->columnSpan(3)
                                            ->helperText(__('filament.integration.fields.default_product_helper')),

                                        Placeholder::make('webhook_subscribed_status')
                                            ->label(__('filament.integration.fields.webhook_subscribed'))
                                            ->content(fn(Get $get) => (bool) $get('metadata.webhook_subscribed')
                                                ? __('filament.integration.status.connected')
                                                : __('filament.integration.status.pending'))
                                            ->columnSpan(2),

                                        Placeholder::make('page_workflow_status')
                                            ->label(__('filament.integration.fields.page_workflow_status'))
                                            ->content(function (Get $get) {
                                                $status = FacebookConnectionStatus::tryFrom((int) $get('status'));

                                                return $status?->label() ?? __('filament.integration.status.pending');
                                            })
                                            ->columnSpan(2),

                                        Placeholder::make('page_connected_at')
                                            ->label(__('filament.integration.fields.page_connected_at'))
                                            ->content(fn(Get $get) => (string) ($get('connected_at') ?: __('filament.integration.sections.never_synced')))
                                            ->columnSpan(2),

                                        Placeholder::make('status_reason')
                                            ->label(__('filament.integration.fields.status_reason'))
                                            ->content(fn(Get $get) => (string) ($get('status_reason') ?: '-'))
                                            ->columnSpan(4),

                                        Hidden::make('metadata.category'),
                                        Hidden::make('metadata.picture'),
                                        Hidden::make('metadata.tasks'),
                                        Hidden::make('metadata.webhook_subscribed'),
                                    ]),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? __('filament.integration.fields.page'))
                            ->visible(
                                fn(?Integration $record, Get $get) =>
                                $record &&
                                    $get('type') == IntegrationType::FACEBOOK_ADS->value &&
                                    $record->entities()->where('type', IntegrationEntityType::PAGE_META->value)->exists()
                            )
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(Get $get) => $get('type') == IntegrationType::FACEBOOK_ADS->value),

                Section::make(__('filament.integration.sections.webhook.title'))
                    ->description(__('filament.integration.sections.webhook.description'))
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('website_auth_header')
                                    ->label(__('filament.integration.fields.auth_header'))
                                    ->content((string) config('marketing.website_v2.auth_header', 'X-Website-Token'))
                                    ->helperText(__('filament.integration.fields.auth_header_helper')),

                                TextInput::make('config.site_id')
                                    ->label(__('filament.integration.fields.site_id'))
                                    ->required()
                                    ->default(fn(Get $get) => $get('config.site_id') ?: Str::lower(Str::random(16)))
                                    ->readOnly()
                                    ->dehydrated()
                                    ->helperText(__('filament.integration.fields.site_id_helper'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('config.webhook_secret')
                                    ->label(__('filament.integration.fields.webhook_secret'))
                                    ->required()
                                    ->default(fn(Get $get) => $get('config.webhook_secret') ?? Str::random(32))
                                    ->disabled(fn(?Integration $record) => $record && $record->status === IntegrationStatus::CONNECTED->value)
                                    ->helperText(fn(?Integration $record) => $record && $record->status === IntegrationStatus::CONNECTED->value
                                        ? __('filament.integration.fields.webhook_secret_locked')
                                        : __('filament.integration.fields.webhook_secret_helper'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                Select::make('config.default_product_id')
                                    ->label(__('filament.integration.fields.default_product'))
                                    ->options(fn() => Product::where('organization_id', Auth::user()->organization_id)->pluck('name', 'id'))
                                    ->searchable()
                                    ->nullable()
                                    ->native(false)
                                    ->helperText(__('filament.integration.fields.default_product_helper')),

                                View::make('filament.components.website-endpoint-tools')
                                    ->viewData(function (Get $get, ?Integration $record) {
                                        $siteId = (string) ($get('config.site_id') ?: Arr::get($record?->config ?? [], 'site_id', ''));
                                        $base = rtrim(url('/'), '/');

                                        return [
                                            'siteId' => $siteId,
                                            'leadEndpoint' => $siteId !== '' ? $base . '/api/v2/website/' . $siteId . '/leads' : null,
                                            'pingEndpoint' => $siteId !== '' ? $base . '/api/v2/website/' . $siteId . '/ping' : null,
                                            'secret' => (string) ($get('config.webhook_secret') ?: Arr::get($record?->config ?? [], 'webhook_secret', '')),
                                        ];
                                    })
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->visible(fn(Get $get) => in_array($get('type'), [
                        IntegrationType::WEBSITE->value
                    ])),

                // Field Mapping
                Section::make(__('filament.integration.sections.field_mapping.title'))
                    ->description(__('filament.integration.sections.field_mapping.description'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->schema([
                        KeyValue::make('field_mapping')
                            ->label(__('filament.integration.fields.field_mapping'))
                            ->keyLabel(__('filament.integration.fields.field_mapping_key'))
                            ->valueLabel(__('filament.integration.fields.field_mapping_value'))
                            ->default([
                                'full_name' => 'name',
                                'phone_number' => 'phone',
                                'email' => 'email',
                            ])
                            ->addActionLabel(__('filament.integration.fields.field_mapping_add'))
                            ->helperText(__('filament.integration.fields.field_mapping_helper'))
                            ->reorderable(),

                        KeyValue::make('config.field_defaults')
                            ->label(__('filament.integration.fields.field_defaults'))
                            ->keyLabel(__('filament.integration.fields.field_defaults_key'))
                            ->valueLabel(__('filament.integration.fields.field_defaults_value'))
                            ->addActionLabel(__('filament.integration.fields.field_defaults_add'))
                            ->helperText(__('filament.integration.fields.field_defaults_helper'))
                            ->reorderable(),
                    ])
                    ->visible(fn(Get $get) => (bool) $get('type')),
            ])
            ->columns(1);
    }
}

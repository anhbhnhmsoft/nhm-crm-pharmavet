<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations\Schemas;

use App\Common\Constants\Marketing\IntegrationType;
use App\Models\Integration;
use App\Models\Product;
use App\Common\Constants\StatusConnect;
use App\Common\Constants\Marketing\IntegrationEntityType;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

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

                                        if ($type === IntegrationType::FACEBOOK_ADS) {
                                            $set('name', fn($current) => $current ?: __('filament.integration.defaults.facebook_name'));
                                            $set('config.webhook_verify_token', fn($current) => $current ?: Str::random(32));
                                        } elseif ($type === IntegrationType::LANDING_PAGE) {
                                            $set('name', fn($current) => $current ?: __('filament.integration.defaults.landing_page_name'));
                                            $set('config.webhook_secret', fn($current) => $current ?: Str::random(32));
                                        } elseif ($type === IntegrationType::WEBSITE) {
                                            $set('name', fn($current) => $current ?: __('filament.integration.defaults.website_name'));
                                            $set('config.webhook_secret', fn($current) => $current ?: Str::random(32));
                                        }
                                    })
                                    ->helperText(fn(Get $get) => IntegrationType::tryFrom($get('type'))?->description())
                                    ->validationMessages([
                                        'required' => 'Vui lòng chọn loại tích hợp.',
                                    ]),

                                TextInput::make('name')
                                    ->label(__('filament.integration.fields.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder(__('filament.integration.fields.name_placeholder'))
                                    ->validationMessages([
                                        'required' => 'Tên tích hợp không được để trống.',
                                        'max' => 'Tên tích hợp không được vượt quá :max ký tự.',
                                    ]),
                            ]),
                    ]),

                Section::make(__('filament.integration.sections.facebook_login.title'))
                    ->description(__('filament.integration.sections.facebook_login.description'))
                    ->schema([
                        Placeholder::make('facebook_status')
                            ->label(false)
                            ->content(function (?Integration $record) {
                                if (!$record || $record->status !== 1) {
                                    return __('filament.integration.sections.facebook_connect_required');
                                }

                                $pagesCount = $record->entities()->where('type', IntegrationEntityType::PAGE_META->value)->count();
                                $lastSync = $record->last_sync_at ? $record->last_sync_at->diffForHumans() : __('filament.integration.sections.never_synced');

                                return __('filament.integration.sections.facebook_connected_summary', [
                                    'count' => $pagesCount,
                                    'last_sync' => $lastSync,
                                ]);
                            }),

                        View::make('filament.components.facebook-oauth-button')
                            ->viewData(fn(?Integration $record) => [
                                'record' => $record ?? 'temp',
                                'isConnected' => (bool) ($record && $record->status === StatusConnect::CONNECTED->value),
                                'pagesCount' => $record ? $record->entities()->where('type', IntegrationEntityType::PAGE_META->value)->count() : 0,
                                'lastSync' => $record ? ($record->last_sync_at ? $record->last_sync_at->diffForHumans() : now()) : now(),
                            ])
                            ->columnSpanFull(),

                        Repeater::make('entities')
                            ->label(__('filament.integration.fields.connected_pages'))
                            ->relationship('entities', modifyQueryUsing: fn($query) => $query->where('type', IntegrationEntityType::PAGE_META->value)->where('status', StatusConnect::CONNECTED->value))
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

                                        Toggle::make('status')
                                            ->label(__('filament.integration.fields.active'))
                                            ->inline(false)
                                            ->onColor('success')
                                            ->offColor('danger')
                                            ->columnSpan(2)
                                            ->extraAttributes(['class' => 'flex justify-center']),

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
                    ->visible(fn(Get $get) => $get('type') == IntegrationType::FACEBOOK_ADS->value)
                    ->collapsible(),

                Section::make(__('filament.integration.sections.webhook.title'))
                    ->description(__('filament.integration.sections.webhook.description'))
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('config.webhook_url')
                                    ->label(__('filament.integration.fields.webhook_url'))
                                    ->url()
                                    ->nullable()
                                    ->maxLength(255)
                                    ->helperText(__('filament.integration.fields.webhook_url_helper'))
                                    ->validationMessages([
                                        'url' => 'URL webhook không hợp lệ. Vui lòng nhập đúng định dạng (https://...).',
                                        'max' => 'URL webhook không được vượt quá :max ký tự.',
                                    ]),

                                TextInput::make('config.webhook_secret')
                                    ->label(__('filament.integration.fields.webhook_secret'))
                                    ->required()
                                    ->default(fn(Get $get) => $get('config.webhook_secret') ?? Str::random(32))
                                    ->disabled(fn(?Integration $record) => $record && $record->status === 1)
                                    ->helperText(fn(?Integration $record) => $record && $record->status === 1
                                        ? __('filament.integration.fields.webhook_secret_locked')
                                        : __('filament.integration.fields.webhook_secret_helper'))
                                    ->validationMessages([
                                        'required' => 'Mã bảo mật webhook không được để trống.',
                                    ]),

                                Select::make('config.default_product_id')
                                    ->label(__('filament.integration.fields.default_product'))
                                    ->options(fn() => Product::where('organization_id', Auth::user()->organization_id)->pluck('name', 'id'))
                                    ->searchable()
                                    ->nullable()
                                    ->native(false)
                                    ->helperText(__('filament.integration.fields.default_product_helper')),
                            ]),
                    ])
                    ->visible(fn(Get $get) => in_array($get('type'), [
                        IntegrationType::LANDING_PAGE->value,
                        IntegrationType::WEBSITE->value
                    ]))
                    ->collapsible(),

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
                                'name' => 'full_name',
                                'phone' => 'phone_number',
                                'email' => 'email',
                            ])
                            ->addActionLabel(__('filament.integration.fields.field_mapping_add'))
                            ->helperText(__('filament.integration.fields.field_mapping_helper'))
                            ->reorderable(),
                    ])
                    ->visible(fn(Get $get) => (bool) $get('type'))
                    ->collapsible()
                    ->collapsed(),
            ])
            ->columns(1);
    }
}

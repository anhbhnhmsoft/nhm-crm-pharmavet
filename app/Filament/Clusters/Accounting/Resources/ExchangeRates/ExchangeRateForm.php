<?php

namespace App\Filament\Clusters\Accounting\Resources\ExchangeRates;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExchangeRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('accounting.exchange_rate.section_basic'))
            ->description(__('accounting.exchange_rate.section_basic_desc'))
            ->columns(2)
            ->schema([
                DatePicker::make('rate_date')
                ->label(__('accounting.exchange_rate.rate_date'))
                ->required()
                ->default(now())
                ->native(false)
                ->displayFormat('d/m/Y'),

                Select::make('from_currency')
                ->label(__('accounting.exchange_rate.from_currency'))
                ->options([
                    'USD' => 'USD - Đô la Mỹ',
                    'EUR' => 'EUR - Euro',
                    'GBP' => 'GBP - Bảng Anh',
                ])
                ->default('USD')
                ->required(),

                Select::make('to_currency')
                ->label(__('accounting.exchange_rate.to_currency'))
                ->options([
                    'VND' => 'VND - Việt Nam Đồng',
                    'EUR' => 'EUR - Euro',
                    'GBP' => 'GBP - Bảng Anh',
                    'JPY' => 'JPY - Yên Nhật',
                    'CNY' => 'CNY - Nhân dân tệ',
                    'THB' => 'THB - Baht Thái',
                    'SGD' => 'SGD - Đô la Singapore',
                    'AUD' => 'AUD - Đô la Úc',
                ])
                ->default('VND')
                ->required(),

                TextInput::make('rate')
                ->label(__('accounting.exchange_rate.rate'))
                ->numeric()
                ->minValue(0)
                ->step(0.000001)
                ->required()
                ->helperText(__('accounting.exchange_rate.rate_help')),
            ]),

            Section::make(__('accounting.exchange_rate.section_other'))
            ->collapsed()
            ->schema([
                Select::make('source')
                ->label(__('accounting.exchange_rate.source'))
                ->options([
                    'manual' => __('accounting.exchange_rate.source_manual'),
                    'api' => __('accounting.exchange_rate.source_api'),
                ])
                ->default('manual')
                ->required(),

                Textarea::make('note')
                ->label(__('accounting.exchange_rate.note'))
                ->rows(2)
                ->maxLength(500),
            ]),
        ]);
    }
}

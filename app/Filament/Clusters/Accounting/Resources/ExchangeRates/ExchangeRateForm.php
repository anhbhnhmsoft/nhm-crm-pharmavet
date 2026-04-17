<?php

namespace App\Filament\Clusters\Accounting\Resources\ExchangeRates;

use App\Common\Constants\CurrencyCode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExchangeRateForm
{
    private const MAX_RATE = 999999999.999999;

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
                        ->options(CurrencyCode::options([
                            CurrencyCode::USD,
                            CurrencyCode::EUR,
                            CurrencyCode::GBP,
                        ]))
                        ->default(CurrencyCode::USD->value)
                        ->required(),

                    Select::make('to_currency')
                        ->label(__('accounting.exchange_rate.to_currency'))
                        ->options(CurrencyCode::options([
                            CurrencyCode::VND,
                            CurrencyCode::EUR,
                            CurrencyCode::GBP,
                            CurrencyCode::JPY,
                            CurrencyCode::CNY,
                            CurrencyCode::THB,
                            CurrencyCode::SGD,
                            CurrencyCode::AUD,
                        ]))
                        ->default(CurrencyCode::VND->value)
                        ->required(),

                    TextInput::make('rate')
                        ->label(__('accounting.exchange_rate.rate'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(self::MAX_RATE)
                        ->step(0.000001)
                        ->required()
                        ->helperText(__('accounting.exchange_rate.rate_help'))
                        ->validationMessages([
                            'max_value' => __('accounting.exchange_rate.rate_max_error'),
                            'numeric' => __('accounting.exchange_rate.rate_numeric_error'),
                        ]),
                ]),

            Section::make(__('accounting.exchange_rate.section_other'))
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

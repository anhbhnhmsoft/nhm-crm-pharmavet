<?php

namespace App\Filament\Clusters\Accounting\Resources\Revenues\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RevenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('accounting.revenue.basic_info'))
                ->schema([
                    DatePicker::make('revenue_date')
                        ->label(__('accounting.revenue.revenue_date'))
                        ->required()
                        ->extraInputAttributes(['required' => false])
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ]),

                    TextInput::make('amount')
                        ->label(__('accounting.revenue.amount'))
                        ->numeric()
                        ->rule('regex:/^[0-9]+$/')
                        ->required()
                        ->extraInputAttributes([
                            'type' => 'text',
                            'inputmode' => 'numeric',
                            'required' => false,
                            'min' => null,
                            'max' => null,
                            'step' => null,
                        ])
                        ->minValue(1)
                        ->prefix("\u{20AB}")
                        ->placeholder('0')
                        ->validationMessages([
                            'required' => __('common.error.required'),
                            'numeric' => __('common.error.numeric'),
                            'regex' => __('common.error.digits_only'),
                            'min' => __('common.error.min_value', ['min' => 1]),
                        ]),

                    TextInput::make('description')
                        ->label(__('accounting.revenue.description'))
                        ->required()
                        ->maxLength(255)
                        ->extraInputAttributes([
                            'required' => false,
                            'maxlength' => null,
                        ])
                        ->columnSpanFull()
                        ->validationMessages([
                            'required' => __('common.error.required'),
                            'max' => __('common.error.max_length', ['max' => 255]),
                        ]),

                    Textarea::make('note')
                        ->label(__('accounting.revenue.note'))
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}

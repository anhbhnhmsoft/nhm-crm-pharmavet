<?php

namespace App\Filament\Clusters\Accounting\Resources\Revenues\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    TextInput::make('amount')
                        ->label(__('accounting.revenue.amount'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('₫')
                        ->placeholder('0'),

                    TextInput::make('description')
                        ->label(__('accounting.revenue.description'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('note')
                        ->label(__('accounting.revenue.note'))
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}

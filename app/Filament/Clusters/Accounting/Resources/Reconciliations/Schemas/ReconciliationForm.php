<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReconciliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('accounting.reconciliation.basic_info'))
                    ->schema([
                        DatePicker::make('reconciliation_date')
                            ->label(__('accounting.reconciliation.reconciliation_date'))
                            ->required()
                            ->default(now()),

                        TextInput::make('ghn_order_code')
                            ->label(__('accounting.reconciliation.ghn_order_code'))
                            ->maxLength(100),

                        TextInput::make('cod_amount')
                            ->label(__('accounting.reconciliation.cod_amount'))
                            ->numeric()
                            ->prefix('VNĐ')
                            ->default(0),

                        TextInput::make('shipping_fee')
                            ->label(__('accounting.reconciliation.shipping_fee'))
                            ->numeric()
                            ->prefix('VNĐ')
                            ->default(0),

                        TextInput::make('storage_fee')
                            ->label(__('accounting.reconciliation.storage_fee'))
                            ->numeric()
                            ->prefix('VNĐ')
                            ->default(0),

                        TextInput::make('total_fee')
                            ->label(__('accounting.reconciliation.total_fee'))
                            ->numeric()
                            ->prefix('VNĐ')
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }
}


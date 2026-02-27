<?php

namespace App\Filament\Clusters\Accounting\Resources\Expenses\Schemas;

use App\Common\Constants\Accounting\ExpenseCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('accounting.expense.basic_info'))
                ->schema([
                    DatePicker::make('expense_date')
                        ->label(__('accounting.expense.expense_date'))
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Select::make('category')
                        ->label(__('accounting.expense.category'))
                        ->options(ExpenseCategory::getOptions())
                        ->required()
                        ->default(ExpenseCategory::OTHER->value),

                    TextInput::make('amount')
                        ->label(__('accounting.expense.amount'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('₫')
                        ->placeholder('0'),

                    TextInput::make('description')
                        ->label(__('accounting.expense.description'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('note')
                        ->label(__('accounting.expense.note'))
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }
}

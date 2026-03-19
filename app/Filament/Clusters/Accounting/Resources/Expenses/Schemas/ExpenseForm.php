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
        return $schema
            ->columns(null)
            ->components([
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
                            ->default(ExpenseCategory::OPERATIONAL->value),

                        TextInput::make('unit_price')
                            ->label('Đơn giá')
                            ->numeric()
                            ->required()
                            ->prefix('₫')
                            ->live()
                            ->afterStateUpdated(fn($state, $set, $get) => self::calculateTotal($set, $get)),

                        TextInput::make('quantity')
                            ->label('Số lượng')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(fn($state, $set, $get) => self::calculateTotal($set, $get)),

                        TextInput::make('amount')
                            ->label(__('accounting.expense.amount'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('₫')
                            ->readOnly()
                            ->helperText('Thành tiền (Đơn giá x Số lượng)')
                            ->columnSpanFull(),

                        TextInput::make('description')
                            ->label(__('accounting.expense.description'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        \Filament\Forms\Components\FileUpload::make('attachments')
                            ->label('Chứng từ/Hóa đơn (PDF/Image)')
                            ->multiple()
                            ->disk('public')
                            ->directory('expense_attachments')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->required()
                            ->helperText('Bắt buộc tải lên chứng từ để audit')
                            ->columnSpanFull(),

                        Textarea::make('note')
                            ->label(__('accounting.expense.note'))
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    protected static function calculateTotal($set, $get): void
    {
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $quantity = (float) ($get('quantity') ?? 0);
        $set('amount', $unitPrice * $quantity);
    }
}

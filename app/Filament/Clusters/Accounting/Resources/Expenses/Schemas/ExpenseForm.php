<?php

namespace App\Filament\Clusters\Accounting\Resources\Expenses\Schemas;

use App\Common\Constants\Accounting\ExpenseCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
                            ->extraInputAttributes(['required' => false])
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('category')
                            ->label(__('accounting.expense.category'))
                            ->options(ExpenseCategory::getOptions())
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->default(ExpenseCategory::OPERATIONAL->value)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'in' => __('common.error.in'),
                            ]),

                        TextInput::make('unit_price')
                            ->label(__('accounting.expense.unit_price'))
                            ->numeric()
                            ->required()
                            ->extraInputAttributes([
                                'type' => 'text',
                                'inputmode' => 'decimal',
                                'required' => false,
                                'min' => null,
                                'max' => null,
                                'step' => null,
                            ])
                            ->minValue(0)
                            ->prefix('₫')
                            ->live()
                            ->afterStateUpdated(fn ($state, $set, $get) => self::calculateTotal($set, $get))
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'numeric' => __('common.error.numeric'),
                                'min' => __('common.error.min_value', ['min' => 0]),
                            ]),

                        TextInput::make('quantity')
                            ->label(__('accounting.expense.quantity'))
                            ->numeric()
                            ->required()
                            ->extraInputAttributes([
                                'type' => 'text',
                                'inputmode' => 'decimal',
                                'required' => false,
                                'min' => null,
                                'max' => null,
                                'step' => null,
                            ])
                            ->minValue(1)
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(fn ($state, $set, $get) => self::calculateTotal($set, $get))
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'numeric' => __('common.error.numeric'),
                                'min' => __('common.error.min_value', ['min' => 1]),
                            ]),

                        TextInput::make('amount')
                            ->label(__('accounting.expense.amount'))
                            ->numeric()
                            ->extraInputAttributes([
                                'type' => 'text',
                                'inputmode' => 'decimal',
                                'min' => null,
                                'max' => null,
                                'step' => null,
                            ])
                            ->minValue(0)
                            ->prefix('₫')
                            ->readOnly()
                            ->dehydrateStateUsing(fn ($state, $get): float => self::calculateAmount($get))
                            ->helperText(__('accounting.expense.amount_help'))
                            ->validationMessages([
                                'numeric' => __('common.error.numeric'),
                                'min' => __('common.error.min_value', ['min' => 0]),
                            ])
                            ->columnSpanFull(),

                        TextInput::make('description')
                            ->label(__('accounting.expense.description'))
                            ->required()
                            ->extraInputAttributes(['required' => false, 'maxlength' => null])
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max' => __('common.error.max_length', ['max' => 255]),
                            ])
                            ->columnSpanFull(),

                        FileUpload::make('attachments')
                            ->label(__('accounting.expense.attachments_upload_label'))
                            ->multiple()
                            ->disk('public')
                            ->directory('expense_attachments')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->helperText(__('accounting.expense.attachments_upload_help'))
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ])
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
        $set('amount', self::calculateAmount($get));
    }

    protected static function calculateAmount(callable $get): float
    {
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $quantity = (float) ($get('quantity') ?? 0);

        return $unitPrice * $quantity;
    }
}

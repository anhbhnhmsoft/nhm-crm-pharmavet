<?php

namespace App\Filament\Clusters\Warehouse\Resources\Orders\Schemas;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Order\PaymentType;
use App\Common\Constants\Order\ServiceType;
use App\Common\Constants\Shipping\RequiredNote;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('order.form.section.basic_info'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label(__('order.form.code'))
                                    ->disabled()
                                    ->required(),

                                Select::make('status')
                                    ->label(__('order.form.status'))
                                    ->options(OrderStatus::toOptions())
                                    ->disabled()
                                    ->required(),

                                Select::make('customer_id')
                                    ->label(__('order.form.customer'))
                                    ->relationship('customer', 'username')
                                    ->searchable()
                                    ->preload()
                                    ->disabled()
                                    ->required(),

                                Select::make('warehouse_id')
                                    ->label(__('order.form.warehouse'))
                                    ->relationship('warehouse', 'name')
                                    ->searchable()
                                    ->preload(),
                            ]),
                    ])
                    ->collapsible(),

                Section::make(__('order.form.section.shipping_info'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('shipping_address')
                                    ->label(__('order.form.shipping_address'))
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Select::make('province_id')
                                    ->label(__('order.form.province'))
                                    ->relationship('province', 'name')
                                    ->searchable()
                                    ->preload(),

                                Select::make('district_id')
                                    ->label(__('order.form.district'))
                                    ->relationship('district', 'name')
                                    ->searchable()
                                    ->preload(),

                                Select::make('ward_id')
                                    ->label(__('order.form.ward'))
                                    ->relationship('ward', 'name')
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('shipping_method')
                                    ->label(__('order.form.shipping_method'))
                                    ->maxLength(50),
                            ]),
                    ])
                    ->collapsible(),

                Section::make(__('order.form.section.ghn_info'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('ghn_order_code')
                                    ->label(__('order.form.ghn_order_code'))
                                    ->disabled()
                                    ->helperText(__('order.form.ghn_order_code_help')),

                                Select::make('ghn_service_type_id')
                                    ->label(__('order.form.ghn_service_type'))
                                    ->options(ServiceType::toOptions())
                                    ->default(2)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                Select::make('ghn_payment_type_id')
                                    ->label(__('order.form.ghn_payment_type'))
                                    ->options(PaymentType::toOptions())
                                    ->default(2)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                Select::make('required_note')
                                    ->label(__('order.form.required_note'))
                                    ->options(RequiredNote::getOptions())
                                    ->default(RequiredNote::ALLOW_VIEWING_NOT_TRIAL->value)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                DateTimePicker::make('ghn_expected_delivery_time')
                                    ->label(__('order.form.ghn_expected_delivery'))
                                    ->disabled()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('ghn_total_fee')
                                    ->label(__('order.form.ghn_total_fee'))
                                    ->numeric()
                                    ->disabled()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ])
                                    ->prefix('VND'),

                                TextInput::make('ghn_status')
                                    ->label(__('order.form.ghn_status'))
                                    ->disabled(),

                                DateTimePicker::make('ghn_posted_at')
                                    ->label(__('order.form.ghn_posted_at'))
                                    ->disabled(),

                                DateTimePicker::make('ghn_cancelled_at')
                                    ->label(__('order.form.ghn_cancelled_at'))
                                    ->disabled(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make(__('order.form.section.package_info'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('weight')
                                    ->label(__('order.form.weight'))
                                    ->numeric()
                                    ->suffix('gram')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                TextInput::make('length')
                                    ->label(__('order.form.length'))
                                    ->numeric()
                                    ->suffix('cm')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                TextInput::make('width')
                                    ->label(__('order.form.width'))
                                    ->numeric()
                                    ->suffix('cm'),

                                TextInput::make('height')
                                    ->label(__('order.form.height'))
                                    ->numeric()
                                    ->suffix('cm'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('insurance_value')
                                    ->label(__('order.form.insurance_value'))
                                    ->numeric()
                                    ->prefix('VND'),

                                TextInput::make('coupon')
                                    ->label(__('order.form.coupon'))
                                    ->maxLength(50),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make(__('order.form.section.financial_info'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_amount')
                                    ->label(__('order.form.total_amount'))
                                    ->numeric()
                                    ->prefix('VND')
                                    ->disabled(),

                                TextInput::make('discount')
                                    ->label(__('order.form.discount'))
                                    ->numeric()
                                    ->prefix('VND')
                                    ->disabled(),

                                TextInput::make('shipping_fee')
                                    ->label(__('order.form.shipping_fee'))
                                    ->numeric()
                                    ->prefix('VND'),

                                TextInput::make('deposit')
                                    ->label(__('order.form.deposit'))
                                    ->numeric()
                                    ->prefix('VND')
                                    ->helperText(__('order.form.deposit_help')),

                                TextInput::make('cod_fee')
                                    ->label(__('order.form.cod_fee'))
                                    ->numeric()
                                    ->prefix('VND'),

                                TextInput::make('ck1')
                                    ->label(__('order.form.ck1'))
                                    ->numeric()
                                    ->suffix('%')
                                    ->disabled(),

                                TextInput::make('ck2')
                                    ->label(__('order.form.ck2'))
                                    ->numeric()
                                    ->suffix('%')
                                    ->disabled(),

                                TextInput::make('amount_recived_from_customer')
                                    ->label(__('order.form.amount_received'))
                                    ->numeric()
                                    ->prefix('VND'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make(__('order.form.section.notes'))
                    ->schema([
                        Textarea::make('note')
                            ->label(__('order.form.note'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make(__('order.form.section.system_info'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('created_at')
                                    ->label(__('order.form.created_at'))
                                    ->content(fn($record) => $record?->created_at?->format('d/m/Y H:i:s')),

                                Placeholder::make('updated_at')
                                    ->label(__('order.form.updated_at'))
                                    ->content(fn($record) => $record?->updated_at?->format('d/m/Y H:i:s')),

                                Placeholder::make('created_by')
                                    ->label(__('order.form.created_by'))
                                    ->content(fn($record) => $record?->createdBy?->name),

                                Placeholder::make('updated_by')
                                    ->label(__('order.form.updated_by'))
                                    ->content(fn($record) => $record?->updatedBy?->name),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

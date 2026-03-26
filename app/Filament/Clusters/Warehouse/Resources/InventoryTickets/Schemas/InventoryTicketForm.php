<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Schemas;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Warehouse\StatusTicket;
use App\Common\Constants\Warehouse\TypeTicket;
use App\Models\Product;
use App\Models\Warehouse;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InventoryTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('warehouse.ticket.section.basic_info'))
                    ->schema([
                        TextInput::make('code')
                            ->label(__('warehouse.ticket.form.code'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn() => 'INV-' . strtoupper(Str::random(8)))
                            ->readOnly()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'unique' => __('common.error.unique'),
                            ]),

                        Select::make('type')
                            ->label(__('warehouse.ticket.form.type'))
                            ->required()
                            ->options(TypeTicket::toArray())
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Reset warehouse fields when type changes
                                if ($state != TypeTicket::TRANSFER->value) {
                                    $set('source_warehouse_id', null);
                                    $set('target_warehouse_id', null);
                                }
                                if ($state != TypeTicket::IMPORT->value) {
                                    $set('order_id', null);
                                    $set('is_sales_return', false);
                                }
                            })
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('order_id')
                            ->label(__('warehouse.ticket.form.order_id'))
                            ->relationship(
                                name: 'order',
                                titleAttribute: 'code',
                                modifyQueryUsing: fn($query) => $query
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->whereIn('status', [OrderStatus::CANCELLED->value, OrderStatus::SHIPPING->value])
                            )
                            ->searchable()
                            ->preload()
                            ->hidden(fn($get) => $get('type') != TypeTicket::IMPORT->value)
                            ->live()
                            ->afterStateUpdated(fn($state, $set) => $set('is_sales_return', (bool) $state)),

                        Toggle::make('is_sales_return')
                            ->label(__('warehouse.ticket.form.is_sales_return'))
                            ->hidden(fn($get) => $get('type') != TypeTicket::IMPORT->value)
                            ->helperText(__('warehouse.ticket.form.is_sales_return_helper_text')),

                        Select::make('status')
                            ->label(__('warehouse.ticket.form.status'))
                            ->required()
                            ->options(StatusTicket::toArray())
                            ->default(StatusTicket::DRAFT->value)
                            ->native(false)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Textarea::make('note')
                            ->label(__('warehouse.ticket.form.note'))
                            ->columnSpanFull()
                            ->rows(3),
                    ])
                    ->columns(3),

                Section::make(__('warehouse.ticket.section.warehouse_info'))
                    ->schema([
                        Select::make('warehouse_id')
                            ->label(__('warehouse.ticket.form.warehouse'))
                            ->required()
                            ->relationship(
                                name: 'warehouse',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn($query) => $query
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn($get) => $get('type') != TypeTicket::TRANSFER->value)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('source_warehouse_id')
                            ->label(__('warehouse.ticket.form.source_warehouse'))
                            ->required()
                            ->relationship(
                                name: 'sourceWarehouse',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn($query) => $query
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn($get) => $get('type') == TypeTicket::TRANSFER->value)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('target_warehouse_id')
                            ->label(__('warehouse.ticket.form.target_warehouse'))
                            ->required()
                            ->relationship(
                                name: 'targetWarehouse',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn($query, $get) => $query
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->where('is_active', true)
                                    ->when(
                                        $get('source_warehouse_id'),
                                        fn($q, $sourceId) =>
                                        $q->where('id', '!=', $sourceId)
                                    )
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn($get) => $get('type') == TypeTicket::TRANSFER->value)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),
                    ])
                    ->columns(2),

                Section::make(__('warehouse.ticket.section.products'))
                    ->schema([
                        Repeater::make('details')
                            ->relationship('details')
                            ->schema([
                                Select::make('product_id')
                                    ->label(__('warehouse.ticket.form.product'))
                                    ->required()
                                    ->relationship(
                                        name: 'product',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn($query) => $query
                                            ->where('organization_id', Auth::user()->organization_id)
                                    )
                                    ->searchable(['name', 'sku'])
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('current_quantity', $product->quantity ?? 0);
                                            }
                                        }
                                    })
                                    ->columnSpan(2)
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('quantity')
                                    ->label(__('warehouse.ticket.form.quantity'))
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->columnSpan(1)
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min_value' => __('common.error.min_value'),
                                    ]),

                                TextInput::make('unit_price')
                                    ->label(__('warehouse.order.form.price'))
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('VND')
                                    ->columnSpan(1)
                                    ->visible(fn() => (bool) config('warehouse.features.advanced_inventory_v1', false)),

                                TextInput::make('batch_no')
                                    ->label(__('warehouse.ticket.form.batch_no'))
                                    ->maxLength(100)
                                    ->columnSpan(1)
                                    ->visible(fn() => (bool) config('warehouse.features.advanced_inventory_v1', false)),

                                TextInput::make('expired_at')
                                    ->label(__('warehouse.ticket.form.expired_at'))
                                    ->type('date')
                                    ->columnSpan(1)
                                    ->visible(fn() => (bool) config('warehouse.features.advanced_inventory_v1', false)),

                                TextInput::make('current_quantity')
                                    ->label(__('warehouse.ticket.form.current_quantity'))
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->columnSpan(1)
                                    ->validationMessages([
                                        'numeric' => __('common.error.numeric'),
                                    ]),
                            ])
                            ->columns(6)
                            ->defaultItems(1)
                            ->addActionLabel(__('common.action.add'))
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                $state['product_id']
                                ? Product::find($state['product_id'])?->name
                                : null
                            ),
                    ]),

                Hidden::make('organization_id')
                    ->default(Auth::user()->organization_id),

                Hidden::make('created_by')
                    ->default(Auth::id()),
            ]);
    }
}

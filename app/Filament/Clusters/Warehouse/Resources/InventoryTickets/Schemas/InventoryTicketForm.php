<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Schemas;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Warehouse\StatusTicket;
use App\Common\Constants\Warehouse\TypeTicket;
use App\Models\Product;
use App\Models\ProductWarehouse;
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
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                // Reset warehouse fields when type changes
                                if ($state != TypeTicket::TRANSFER->value) {
                                    $set('source_warehouse_id', null);
                                    $set('target_warehouse_id', null);
                                }
                                if ($state != TypeTicket::IMPORT->value) {
                                    $set('order_id', null);
                                    $set('is_sales_return', false);
                                }

                                static::refreshDetailStockSnapshots($get, $set);
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
                    ->columns(3)
                    ->columnSpanFull(),

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
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => static::refreshDetailStockSnapshots($get, $set))
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
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => static::refreshDetailStockSnapshots($get, $set))
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
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('warehouse.ticket.section.products'))
                    ->schema([
                        Repeater::make('details')
                            ->label(__('warehouse.ticket.section.products'))
                            ->hiddenLabel()
                            ->relationship('details')
                            ->minItems(1)
                            ->schema([
                                Select::make('product_id')
                                    ->label(__('warehouse.ticket.form.product'))
                                    ->required()
                                    ->options(fn ($get): array => static::getProductOptions($get))
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search, $get): array => static::getProductOptions($get, $search))
                                    ->getOptionLabelUsing(fn ($value, $get): ?string => static::getProductOptionLabel((int) $value, $get))
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, $get) {
                                        static::setDetailStockSnapshot($set, $get, (int) $state);
                                    })
                                    ->columnSpan(2)
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('quantity')
                                    ->label(__('warehouse.ticket.form.quantity'))
                                    ->required()
                                    ->integer()
                                    ->default(1)
                                    ->minValue(1)
                                    ->extraInputAttributes([
                                        'type' => 'text',
                                        'inputmode' => 'numeric',
                                        'required' => false,
                                        'min' => null,
                                        'max' => null,
                                        'step' => null,
                                    ])
                                    ->columnSpan(1)
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'integer' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 1]),
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

                                TextInput::make('stock_quantity_display')
                                    ->label(__('warehouse.ticket.form.current_quantity'))
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(false)
                                    ->columnSpan(1)
                                    ->validationMessages([
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                TextInput::make('pending_quantity_display')
                                    ->label(__('warehouse.ticket.form.pending_quantity_display'))
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(false)
                                    ->columnSpan(1)
                                    ->validationMessages([
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                TextInput::make('current_quantity')
                                    ->label(__('warehouse.reports.available_stock'))
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        static::setDetailStockSnapshot(
                                            $set,
                                            $get,
                                            (int) ($get('product_id') ?? 0)
                                        );
                                    })
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
                            ->validationMessages([
                                'min' => __('common.error.min_items', ['min' => 1]),
                            ])
                            ->itemLabel(
                                fn(array $state): ?string =>
                                $state['product_id']
                                ? Product::find($state['product_id'])?->name
                                : null
                            ),
                    ])
                    ->columnSpanFull(),

                Hidden::make('organization_id')
                    ->default(Auth::user()->organization_id),

                Hidden::make('created_by')
                    ->default(Auth::id()),
            ]);
    }

    protected static function refreshDetailStockSnapshots(callable $get, callable $set): void
    {
        $details = $get('details') ?? [];

        foreach ($details as $index => $detail) {
            $snapshot = static::resolveStockSnapshotFromTicketContext(
                type: (int) ($get('type') ?? 0),
                warehouseId: (int) ($get('warehouse_id') ?? 0),
                sourceWarehouseId: (int) ($get('source_warehouse_id') ?? 0),
                productId: (int) ($detail['product_id'] ?? 0),
            );

            $set("details.{$index}.stock_quantity_display", $snapshot['quantity']);
            $set("details.{$index}.pending_quantity_display", $snapshot['pending']);
            $set("details.{$index}.current_quantity", $snapshot['available']);
        }
    }

    protected static function setDetailStockSnapshot(callable $set, callable $get, int $productId): void
    {
        $snapshot = static::resolveStockSnapshotFromContext($get, $productId);

        $set('stock_quantity_display', $snapshot['quantity']);
        $set('pending_quantity_display', $snapshot['pending']);
        $set('current_quantity', $snapshot['available']);
    }

    protected static function resolveStockSnapshotFromContext(callable $get, int $productId): array
    {
        return static::resolveStockSnapshotFromTicketContext(
            type: (int) ($get('type') ?? $get('../../type') ?? 0),
            warehouseId: (int) ($get('warehouse_id') ?? $get('../../warehouse_id') ?? 0),
            sourceWarehouseId: (int) ($get('source_warehouse_id') ?? $get('../../source_warehouse_id') ?? 0),
            productId: $productId,
        );
    }

    protected static function resolveStockSnapshotFromTicketContext(
        int $type,
        int $warehouseId,
        int $sourceWarehouseId,
        int $productId,
    ): array {
        $stockWarehouseId = $type === TypeTicket::TRANSFER->value
            ? $sourceWarehouseId
            : $warehouseId;

        return static::resolveStockSnapshot($stockWarehouseId, $productId);
    }

    protected static function getProductOptions(callable $get, ?string $search = null): array
    {
        $type = (int) ($get('type') ?? $get('../../type') ?? 0);
        $warehouseId = (int) ($get('warehouse_id') ?? $get('../../warehouse_id') ?? 0);
        $sourceWarehouseId = (int) ($get('source_warehouse_id') ?? $get('../../source_warehouse_id') ?? 0);

        $query = Product::query()
            ->where('organization_id', Auth::user()->organization_id)
            ->orderBy('name');

        if (filled($search)) {
            $query->where(function ($productQuery) use ($search): void {
                $productQuery
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%');
            });
        }

        $products = $query->get(['id', 'name', 'sku']);

        if ($products->isEmpty()) {
            return [];
        }

        $snapshotMap = static::resolveStockSnapshotMap(
            type: $type,
            warehouseId: $warehouseId,
            sourceWarehouseId: $sourceWarehouseId,
            productIds: $products->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        return $products
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => static::formatProductOptionLabel(
                    $product,
                    $snapshotMap[(int) $product->id]['quantity'] ?? 0,
                ),
            ])
            ->all();
    }

    protected static function getProductOptionLabel(int $productId, callable $get): ?string
    {
        if ($productId <= 0) {
            return null;
        }

        $product = Product::query()
            ->where('organization_id', Auth::user()->organization_id)
            ->find($productId, ['id', 'name', 'sku']);

        if (! $product) {
            return null;
        }

        $snapshot = static::resolveStockSnapshotFromContext($get, $productId);

        return static::formatProductOptionLabel($product, $snapshot['quantity']);
    }

    protected static function resolveStockSnapshotMap(
        int $type,
        int $warehouseId,
        int $sourceWarehouseId,
        array $productIds,
    ): array {
        $stockWarehouseId = $type === TypeTicket::TRANSFER->value
            ? $sourceWarehouseId
            : $warehouseId;

        if ($stockWarehouseId <= 0 || $productIds === []) {
            return collect($productIds)
                ->mapWithKeys(fn (int $productId): array => [$productId => static::emptyStockSnapshot()])
                ->all();
        }

        $stocks = ProductWarehouse::query()
            ->where('warehouse_id', $stockWarehouseId)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'quantity', 'pending_quantity'])
            ->keyBy('product_id');

        return collect($productIds)
            ->mapWithKeys(function (int $productId) use ($stocks): array {
                $stock = $stocks->get($productId);

                return [
                    $productId => [
                        'quantity' => (int) ($stock?->quantity ?? 0),
                        'pending' => (int) ($stock?->pending_quantity ?? 0),
                        'available' => max(0, (int) ($stock?->quantity ?? 0) - (int) ($stock?->pending_quantity ?? 0)),
                    ],
                ];
            })
            ->all();
    }

    protected static function resolveStockSnapshot(int $warehouseId, int $productId): array
    {
        if ($warehouseId <= 0 || $productId <= 0) {
            return static::emptyStockSnapshot();
        }

        $stock = ProductWarehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first(['quantity', 'pending_quantity']);

        return [
            'quantity' => (int) ($stock?->quantity ?? 0),
            'pending' => (int) ($stock?->pending_quantity ?? 0),
            'available' => max(0, (int) ($stock?->quantity ?? 0) - (int) ($stock?->pending_quantity ?? 0)),
        ];
    }

    protected static function emptyStockSnapshot(): array
    {
        return [
            'quantity' => 0,
            'pending' => 0,
            'available' => 0,
        ];
    }

    protected static function formatProductOptionLabel(Product $product, int $currentQuantity): string
    {
        $baseLabel = (string) $product->name;

        if (filled($product->sku)) {
            $baseLabel .= ' - ' . $product->sku;
        }

        return $baseLabel . ' (' . __('warehouse.ticket.form.current_quantity') . ': ' . number_format($currentQuantity) . ')';
    }
}

<?php

namespace App\Filament\Clusters\Warehouse\Resources\Warehouses\Schemas;

use App\Common\Constants\User\UserRole;
use App\Models\Product;
use App\Models\Warehouse;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('warehouse.form.initial_stock_section'))
                    ->description(__('warehouse.form.initial_stock_description'))
                    ->schema([
                        Repeater::make('productWarehouses')
                            ->label(__('warehouse.navigation.product'))
                            ->hiddenLabel()
                            ->relationship(
                                name: 'productWarehouses',
                                modifyQueryUsing: fn ($query) => $query->whereHas(
                                    'product',
                                    fn ($productQuery) => $productQuery->where('organization_id', Auth::user()->organization_id)
                                ),
                            )
                            ->defaultItems(1)
                            ->schema([
                                Select::make('product_id')
                                    ->label(__('warehouse.navigation.product_name'))
                                    ->required()
                                    ->extraInputAttributes(['required' => false])
                                    ->options(fn (): array => self::getWarehouseProductOptions())
                                    ->getSearchResultsUsing(fn (string $search): array => self::getWarehouseProductOptions($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => self::getWarehouseProductOptionLabel($value))
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpanFull()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'distinct' => __('common.error.distinct_product'),
                                    ]),

                                TextInput::make('quantity')
                                    ->label(__('warehouse.navigation.product_quantity'))
                                    ->required()
                                    ->integer()
                                    ->minValue(0)
                                    ->extraInputAttributes([
                                        'type' => 'text',
                                        'inputmode' => 'numeric',
                                        'required' => false,
                                        'min' => null,
                                        'max' => null,
                                        'step' => null,
                                    ])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'integer' => __('common.error.integer'),
                                        'min' => __('common.error.min_value', ['min' => 0]),
                                    ]),

                                TextInput::make('pending_quantity')
                                    ->label(__('warehouse.navigation.product_pending_quantity'))
                                    ->required()
                                    ->integer()
                                    ->minValue(0)
                                    ->extraInputAttributes([
                                        'type' => 'text',
                                        'inputmode' => 'numeric',
                                        'required' => false,
                                        'min' => null,
                                        'max' => null,
                                        'step' => null,
                                    ])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'integer' => __('common.error.integer'),
                                        'min' => __('common.error.min_value', ['min' => 0]),
                                    ]),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->maxItems(10)
                            ->required(),
                    ])
                    ->columnSpanFull(),

                Section::make(__('warehouse.form.basic_info_section'))
                    ->description(__('warehouse.form.basic_info_description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('warehouse.form.name'))
                            ->required()
                            ->extraInputAttributes(['required' => false, 'maxlength' => null])
                            ->maxLength(255)
                            ->scopedUnique(
                                Warehouse::class,
                                'name',
                                ignoreRecord: true,
                                modifyQueryUsing: fn (Builder $query) => $query->where('organization_id', Auth::user()->organization_id),
                            )
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max' => __('common.error.max_length', ['max' => 255]),
                                'unique' => __('common.error.unique'),
                            ])
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $slug = substr(Str::slug($state, '-'), 0, 3) . Str::random(5);
                                    $set('code', Str::upper($slug));
                                }
                            }),

                        TextInput::make('code')
                            ->label(__('warehouse.form.code'))
                            ->extraInputAttributes(['required' => false, 'maxlength' => null])
                            ->maxLength(255)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $slug = substr(Str::slug($state, '-'), 0, 3) . Str::random(5);
                                    $set('code', Str::upper($slug));
                                }
                            })
                            ->scopedUnique(
                                Warehouse::class,
                                'code',
                                ignoreRecord: true,
                                modifyQueryUsing: fn (Builder $query) => $query->where('organization_id', Auth::user()->organization_id),
                            )
                            ->validationMessages([
                                'max' => __('common.error.max_length', ['max' => 255]),
                                'unique' => __('common.error.unique'),
                            ])
                            ->readOnly(),

                        TextInput::make('phone')
                            ->label(__('warehouse.form.phone'))
                            ->tel()
                            ->required()
                            ->extraInputAttributes(['required' => false, 'maxlength' => null, 'type' => 'text', 'inputmode' => 'tel'])
                            ->maxLength(15)
                            ->rules([
                                'regex:/^(0|(\+84))[35789][0-9]{8}$/',
                            ])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'regex' => __('common.error.phone_invalid'),
                                'max' => __('common.error.max_length', ['max' => 15]),
                            ]),

                        Toggle::make('is_active')
                            ->label(__('warehouse.form.is_active'))
                            ->default(true)
                            ->disabled(fn ($livewire) => $livewire instanceof CreateRecord),

                        Textarea::make('note')
                            ->label(__('warehouse.form.note'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull(),

                Section::make(__('warehouse.form.shipping_address_section'))
                    ->description(__('warehouse.form.shipping_address_description'))
                    ->schema([
                        Select::make('province_id')
                            ->label(__('warehouse.form.province'))
                            ->relationship(name: 'province', titleAttribute: 'name')
                            ->searchable()
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('district_id', null))
                            ->native(false)
                            ->preload()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('district_id')
                            ->label(__('warehouse.form.district'))
                            ->relationship(name: 'district', titleAttribute: 'name', modifyQueryUsing: function ($query, $get) {
                                $query->where('province_id', $get('province_id'));
                            })
                            ->searchable()
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('ward_id', null))
                            ->native(false)
                            ->preload()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Select::make('ward_id')
                            ->label(__('warehouse.form.ward'))
                            ->relationship(name: 'ward', titleAttribute: 'name', modifyQueryUsing: function ($query, $get) {
                                $query->where('district_id', $get('district_id'));
                            })
                            ->searchable()
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->live()
                            ->native(false)
                            ->preload()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        TextInput::make('address')
                            ->label(__('warehouse.form.address'))
                            ->required()
                            ->extraInputAttributes(['required' => false, 'maxlength' => null])
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max' => __('common.error.max_length', ['max' => 255]),
                            ]),

                        Select::make('deliveryProvinces')
                            ->label(__('warehouse.form.delivery_provinces'))
                            ->relationship(
                                titleAttribute: 'name',
                                name: 'deliveryProvinces',
                                modifyQueryUsing: fn ($query) => $query->select([
                                    'provinces.id',
                                    'provinces.name',
                                ]),
                            )
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 3])
                    ->columnSpanFull(),

                Section::make(__('warehouse.form.management_sender_section'))
                    ->description(__('warehouse.form.management_sender_description'))
                    ->schema([
                        Select::make('manager')
                            ->label(__('warehouse.navigation.manager'))
                            ->relationship(
                                name: 'manager',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->where('role', UserRole::WAREHOUSE->value),
                            )
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->searchable()
                            ->preload()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        TextInput::make('manager_phone')
                            ->label(__('warehouse.navigation.manager_phone'))
                            ->tel()
                            ->required()
                            ->extraInputAttributes(['required' => false, 'maxlength' => null, 'type' => 'text', 'inputmode' => 'tel'])
                            ->maxLength(15)
                            ->rules([
                                'regex:/^(0|(\+84))[35789][0-9]{8}$/',
                            ])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'regex' => __('common.error.phone_invalid'),
                                'max' => __('common.error.max_length', ['max' => 15]),
                            ]),

                        TextInput::make('sender_name')
                            ->label(__('warehouse.navigation.sender_name'))
                            ->required()
                            ->extraInputAttributes(['required' => false, 'maxlength' => null])
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max' => __('common.error.max_length', ['max' => 255]),
                            ]),

                        Textarea::make('sender_info')
                            ->label(__('warehouse.navigation.sender_info'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->rows(3)
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function getWarehouseProductOptions(?string $search = null): array
    {
        return Product::query()
            ->where('organization_id', Auth::user()->organization_id)
            ->where('is_business_product', true)
            ->when(
                filled($search),
                fn (Builder $query) => $query->where(function (Builder $productQuery) use ($search): void {
                    $productQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function getWarehouseProductOptionLabel(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Product::query()
            ->withTrashed()
            ->whereKey($value)
            ->value('name');
    }
}

<?php

namespace App\Filament\Clusters\Warehouse\Resources\Warehouses\Schemas;

use App\Common\Constants\User\UserRole;
use App\Models\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('warehouse.form.unit_warehouse'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('warehouse.form.name'))
                            ->required()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max_length' => __('common.error.max_length', ['max' => 255]),
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
                            ->required()
                            ->maxLength(255)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $slug = substr(Str::slug($state, '-'), 0, 3) . Str::random(5);
                                    $set('code', Str::upper($slug));
                                }
                            })
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max_length' => __('common.error.max_length', ['max' => 255]),
                                'unique' => __('common.error.unique'),
                            ])
                            ->readOnly(),
                        TextInput::make('phone')
                            ->label(__('warehouse.form.phone'))
                            ->tel()
                            ->required()
                            ->maxLength(255),
                        Textarea::make('note')
                            ->label(__('warehouse.form.note'))
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label(__('warehouse.form.is_active'))
                            ->default(true)
                            ->disabled(fn($livewire) => $livewire instanceof CreateRecord),
                    ])->columns(2),

                Section::make(__('warehouse.form.address'))
                    ->schema([
                        Select::make('province_id')
                            ->label(__('warehouse.form.province'))
                            ->relationship(name: 'province', titleAttribute: 'name')
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('district_id', null))
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
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('ward_id', null))
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
                            ->live()
                            ->native(false)
                            ->preload()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        TextInput::make('address')
                            ->label(__('warehouse.form.address'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max_length' => __('common.error.max_length', ['max' => 255]),
                            ]),
                    ])->columns(3),

                Section::make(__('warehouse.navigation.delivery'))
                    ->schema([
                        Select::make('deliveryProvinces')
                            ->label(__('warehouse.navigation.delivery_provinces'))
                            ->relationship(titleAttribute: 'name', name: 'deliveryProvinces', modifyQueryUsing: fn($query) => $query->select([
                                'provinces.id',
                                'provinces.name',
                            ]))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('warehouse.navigation.management'))
                    ->schema([
                        Select::make('manager')
                            ->label(__('warehouse.navigation.manager'))
                            ->relationship(name: 'manager', titleAttribute: 'name', modifyQueryUsing: fn($query) => $query->where('organization_id', Auth::user()->organization_id)->where('role', UserRole::WAREHOUSE->value))
                            ->searchable()
                            ->preload(),
                        TextInput::make('manager_phone')
                            ->label(__('warehouse.navigation.manager_phone'))
                            ->tel()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max_length' => __('common.error.max_length', ['max' => 255]),
                            ]),
                        TextInput::make('sender_name')
                            ->label(__('warehouse.navigation.sender_name'))
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max_length' => __('common.error.max_length', ['max' => 255]),
                            ]),
                        Textarea::make('sender_info')
                            ->label(__('warehouse.navigation.sender_info'))
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max_length' => __('common.error.max_length', ['max' => 255]),
                            ]),
                    ])->columns(2),
                Section::make()
                    ->schema([
                        Repeater::make('productWarehouses')
                            ->label(__('warehouse.navigation.product'))
                            ->relationship(
                                name: 'productWarehouses',
                                modifyQueryUsing: fn($query) =>
                                $query->whereHas('product', fn($q) => $q->where('organization_id', Auth::user()->organization_id))
                            )
                            ->schema([

                                Select::make('product_id')
                                    ->label(__('warehouse.navigation.product_name'))
                                    ->required()
                                    ->options(fn() => Product::where('organization_id', Auth::user()->organization_id)->where('is_business_product', true)->pluck('name', 'id'))
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpanFull()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('quantity')
                                    ->label(__('warehouse.navigation.product_quantity'))
                                    ->required()
                                    ->numeric()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                TextInput::make('pending_quantity')
                                    ->label(__('warehouse.navigation.product_pending_quantity'))
                                    ->required()
                                    ->numeric()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                    ]),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->maxItems(10)
                            ->required()

                    ]),
            ]);
    }
}

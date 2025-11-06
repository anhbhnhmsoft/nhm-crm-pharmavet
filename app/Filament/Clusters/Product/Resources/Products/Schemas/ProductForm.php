<?php

namespace App\Filament\Clusters\Product\Resources\Products\Schemas;

use App\Models\User;
use App\Models\Team;
use App\Utils\Helper;
use App\Common\Constants\Product\TypeVAT;
use App\Common\Constants\Organization\ProductField;
use App\Common\Constants\Team\TeamType;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament.product.product_info'))
                    ->description(__('filament.product.enter_basic_product_infomation'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament.product.name'))
                                    ->required()
                                    ->minLength(3)
                                    ->maxLength(255)
                                    ->placeholder(__('filament.product.tooltip_name'))
                                    ->columnSpanFull()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                        if (!empty($state)) {
                                            $slug = str($state)->slug()->toString();
                                            $set('slug', $slug);

                                            // Auto generate SKU nếu chưa có
                                            if (empty($get('sku'))) {
                                                $sku = Helper::generateSKU($state);
                                                $set('sku', $sku);
                                            }
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'min' => __('common.error.min_length', ['min' => 3]),
                                        'max' => __('common.error.max_length', ['max' => 255]),
                                    ]),

                                TextInput::make('sku')
                                    ->label(__('filament.product.code_sku'))
                                    ->unique(ignoreRecord: true)
                                    ->required()
                                    ->minLength(3)
                                    ->maxLength(100)
                                    ->placeholder(__('filament.product.tooltip_sku'))
                                    ->helperText(__('filament.product.tooltip_sku_place'))
                                    ->suffixAction(
                                        Action::make('generateSKU')
                                            ->icon('heroicon-o-arrow-path')
                                            ->tooltip(__('filament.product.generate_sku'))
                                            ->action(function (callable $set, Get $get) {
                                                $name = $get('name');
                                                if (!empty($name)) {
                                                    $sku = Helper::generateSKU($name);
                                                    $set('sku', $sku);
                                                }
                                            })
                                    )
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'unique' => __('common.error.unique'),
                                        'min' => __('common.error.min_length', ['min' => 3]),
                                        'max' => __('common.error.max_length', ['max' => 100]),
                                    ]),

                                TextInput::make('barcode')
                                    ->label(__('filament.product.barcode'))
                                    ->unique(ignoreRecord: true)
                                    ->minLength(8)
                                    ->maxLength(100)
                                    ->placeholder(__('filament.product.barcode_tooltip'))
                                    ->suffixAction(
                                        Action::make('generateBarcode')
                                            ->icon('heroicon-o-qr-code')
                                            ->tooltip(__('filament.product.generate_barcode'))
                                            ->action(function (callable $set, Get $get) {
                                                $barcode = Helper::generateBarcode();
                                                $set('barcode', $barcode);
                                            })
                                    )
                                    ->helperText(__('filament.product.barcode_helper'))
                                    ->validationMessages([
                                        'unique' => __('common.error.unique'),
                                        'min' => __('common.error.min_length', ['min' => 8]),
                                        'max' => __('common.error.max_length', ['max' => 100]),
                                    ]),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('unit')
                                    ->label(__('filament.product.unit'))
                                    ->maxLength(50)
                                    ->placeholder(__('filament.product.unit_tooltip'))
                                    ->default('Cái')
                                    ->validationMessages([
                                        'max' => __('common.error.max_length', ['max' => 50]),
                                    ]),

                                TextInput::make('weight')
                                    ->label(__('filament.product.weight'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(999999999)
                                    ->default(0)
                                    ->placeholder('0')
                                    ->suffix('g')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'max' => __('common.error.max_value', ['max' => 999999999]),
                                    ]),

                                TextInput::make('quantity')
                                    ->label(__('filament.product.quantity'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(999999999)
                                    ->default(0)
                                    ->placeholder('0')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'max' => __('common.error.max_value', ['max' => 999999999]),
                                    ]),
                            ]),

                        RichEditor::make('description')
                            ->label(__('filament.product.description'))
                            ->placeholder(__('filament.product.description_detail'))
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'link',
                            ]),

                        FileUpload::make('images')
                            ->label(__('filament.product.image'))
                            ->image()
                            ->maxFiles(1)
                            ->imageEditor()
                            ->columnSpanFull()
                            ->panelLayout('grid')
                            ->helperText(__('filament.product.max_image'))
                            ->directory('products'),
                    ]),

                Section::make(__('filament.product.price_and_tax'))
                    ->description(__('filament.product.setup_price_and_tax'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('cost_price')
                                    ->label(__('filament.product.cost_price'))
                                    ->numeric()
                                    ->prefix('₫')
                                    ->step(1000)
                                    ->required()
                                    ->minValue(0)
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Get $get, callable $set) {
                                        $salePrice = $get('sale_price');
                                        if ($salePrice !== null && $state > $salePrice) {
                                            $set('cost_price', $salePrice);
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                TextInput::make('sale_price')
                                    ->label(__('filament.product.sale_price'))
                                    ->numeric()
                                    ->prefix('₫')
                                    ->step(1000)
                                    ->minValue(0)
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Get $get, callable $set) {
                                        $costPrice = $get('cost_price');
                                        if ($costPrice !== null && $state < $costPrice) {
                                            $set('sale_price', $costPrice);
                                        }
                                    })
                                    ->helperText(__('filament.product.cost_must_be_less_than_sale'))
                                    ->validationMessages([
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                Select::make('type')
                                    ->label(__('filament.product.type'))
                                    ->options(ProductField::toOptions())
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('type_vat')
                                    ->label(__('filament.product.type_vat'))
                                    ->options(TypeVAT::toOptions())
                                    ->required()
                                    ->default(TypeVAT::INCLUSIVE->value)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                        $type = TypeVAT::tryFrom((int) $state);
                                        if (! $type) {
                                            return;
                                        }

                                        // Cập nhật tỷ lệ VAT mặc định
                                        $defaultRate = $type->defaultRate();
                                        $set('vat_rate', $defaultRate);

                                        // Nếu có giá bán, có thể cập nhật helperText (nhưng không nên tự động đổi giá)
                                        $salePrice = (float) ($get('sale_price') ?? 0);
                                        if ($salePrice > 0 && $defaultRate > 0) {
                                            $result = $type->calculateFinalPrice($salePrice, $defaultRate);
                                            if ($type === TypeVAT::EXCLUSIVE) {
                                                $set('sale_price', $result['final_price']);
                                            }
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('vat_rate')
                                    ->label(__('filament.product.vat_percent'))
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->disabled(fn(Get $get) => $get('type_vat') === TypeVAT::NO_VAT->value)
                                    ->helperText(function (Get $get) {
                                        $type = TypeVAT::tryFrom((int) $get('type_vat'));
                                        $vatRate = (float) ($get('vat_rate') ?? 0);
                                        $salePrice = (float) ($get('sale_price') ?? 0);

                                        if (! $type || $salePrice <= 0) {
                                            return '';
                                        }

                                        $result = $type->calculateFinalPrice($salePrice, $vatRate);

                                        return match ($type) {
                                            TypeVAT::NO_VAT, TypeVAT::ZERO_RATED => __('filament.product.no_vat_applied'),

                                            TypeVAT::INCLUSIVE => __('filament.product.vat_inclusive_info', [
                                                'price' => number_format($result['base_price'], 0),
                                                'vat'   => number_format($result['vat_amount'], 0),
                                            ]),

                                            TypeVAT::EXCLUSIVE,
                                            TypeVAT::STANDARD,
                                            TypeVAT::REDUCED,
                                            TypeVAT::EIGHT_PERCENT => __('filament.product.vat_exclusive_info', [
                                                'vat'   => number_format($result['vat_amount'], 0),
                                                'total' => number_format($result['final_price'], 0),
                                            ]),

                                            default => '',
                                        };
                                    })
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric'  => __('common.error.numeric'),
                                        'min'      => __('common.error.min_value', ['min' => 0]),
                                        'max'      => __('common.error.max_value', ['max' => 100]),
                                    ]),
                            ]),
                    ]),

                Section::make(__('filament.product.dimension_and_quantity'))
                    ->description(__('filament.product.dimension_and_quantity_info'))
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('lenght')
                                    ->label(__('filament.product.length'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('cm')
                                    ->placeholder(__('filament.product.placeholder_dimension'))
                                    ->validationMessages([
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                TextInput::make('width')
                                    ->label(__('filament.product.width'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('cm')
                                    ->placeholder(__('filament.product.placeholder_dimension'))
                                    ->validationMessages([
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                TextInput::make('height')
                                    ->label(__('filament.product.height'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('cm')
                                    ->placeholder(__('filament.product.placeholder_dimension'))
                                    ->validationMessages([
                                        'numeric' => __('common.error.numeric'),
                                    ]),
                            ]),
                    ]),

                Section::make(__('filament.product.attributes'))
                    ->description(__('filament.product.attributes_info'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Repeater::make('attributes')
                            ->label(__('filament.product.attribute_list'))
                            ->relationship('attributes')
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament.product.attribute_name'))
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder(__('filament.product.attribute_name_placeholder'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('value')
                                    ->label(__('filament.product.attribute_value'))
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder(__('filament.product.attribute_value_placeholder'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel(__('filament.product.add_attribute'))
                            ->reorderable()
                            ->collapsible(),
                    ])
                    ->hidden(fn(Get $get): bool => !$get('has_attributes')),


                Section::make(__('filament.product.assignments'))
                    ->description(__('filament.product.assignments_info'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Select::make('sales_team_id')
                            ->label(__('filament.product.sales_team'))
                            ->options(
                                fn() =>
                                Team::where('type', TeamType::SALE->value)
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->placeholder(__('filament.product.select_team_first')),

                        Select::make('sales_user_ids')
                            ->label(__('filament.product.sales_staff'))
                            ->multiple()
                            ->relationship(
                                name: 'salesUsers',
                                titleAttribute: 'name',
                                modifyQueryUsing: function ($query, Get $get) {
                                    if ($teamId = $get('sales_team_id')) {
                                        $query->where('team_id', $teamId);
                                    } else {
                                        $query->whereHas(
                                            'team',
                                            fn($q) =>
                                            $q->where('type', TeamType::SALE->value)
                                        );
                                    }
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn(Get $get) => !$get('sales_team_id'))
                            ->helperText(__('filament.product.select_team_to_load_users'))
                            ->placeholder(__('filament.product.select_staff_placeholder')),

                        Select::make('marketing_team_id')
                            ->label(__('filament.product.marketing_team'))
                            ->options(
                                fn() =>
                                Team::where('type', TeamType::MARKETING->value)
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->placeholder(__('filament.product.select_team_first')),

                        Select::make('marketing_user_ids')
                            ->label(__('filament.product.marketing_staff'))
                            ->multiple()
                            ->relationship(
                                name: 'marketingUsers',
                                titleAttribute: 'name',
                                modifyQueryUsing: function ($query, Get $get) {
                                    if ($teamId = $get('marketing_team_id')) {
                                        $query->where('team_id', $teamId);
                                    } else {
                                        $query->whereHas(
                                            'team',
                                            fn($q) =>
                                            $q->where('type', TeamType::MARKETING->value)
                                        );
                                    }
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn(Get $get) => !$get('marketing_team_id'))
                            ->helperText(__('filament.product.select_team_to_load_users'))
                            ->placeholder(__('filament.product.select_staff_placeholder')),

                        Select::make('cskh_team_id')
                            ->label(__('filament.product.cskh_team'))
                            ->options(
                                fn() =>
                                Team::where('type', TeamType::CSKH->value)
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->placeholder(__('filament.product.select_team_first')),

                        Select::make('cskh_user_ids')
                            ->label(__('filament.product.cskh_staff'))
                            ->multiple()
                            ->relationship(
                                name: 'cskhUsers',
                                titleAttribute: 'name',
                                modifyQueryUsing: function ($query, Get $get) {
                                    if ($teamId = $get('cskh_team_id')) {
                                        $query->where('team_id', $teamId);
                                    } else {
                                        $query->whereHas(
                                            'team',
                                            fn($q) =>
                                            $q->where('type', TeamType::CSKH->value)
                                        );
                                    }
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn(Get $get) => !$get('cskh_team_id'))
                            ->helperText(__('filament.product.select_team_to_load_users'))
                            ->placeholder(__('filament.product.select_staff_placeholder')),
                    ]),
                Section::make(__('filament.product.status'))
                    ->description(__('filament.product.config_status'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_business_product')
                                    ->label(__('filament.product.stop_bussiness'))
                                    ->default(false)
                                    ->helperText(__('filament.product.stop_bussiness_whene'))
                                    ->inline(false)
                                    ->disabled((fn($livewire) => ($livewire instanceof CreateRecord))),

                                Toggle::make('has_attributes')
                                    ->label(__('filament.product.has_attributes'))
                                    ->default(false)
                                    ->helperText(__('filament.product.has_attributes_info'))
                                    ->live()
                                    ->inline(false),
                            ]),
                    ]),
            ]);
    }
}

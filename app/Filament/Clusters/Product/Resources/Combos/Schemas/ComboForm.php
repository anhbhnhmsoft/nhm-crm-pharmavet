<?php

namespace App\Filament\Clusters\Product\Resources\Combos\Schemas;

use App\Models\Product;
use App\Utils\Helper;
use App\Common\Constants\Product\StatusCombo;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class ComboForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([

            Section::make(__('filament.combo.basic_info'))
                ->schema([
                    Grid::make(2)->schema([

                        TextInput::make('name')
                            ->label(__('filament.combo.name'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->live(debounce: 1000, onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set) => $set('code', Helper::generateComboCode($state)))
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_length', ['min' => 3]),
                                'max' => __('common.error.max_length', ['max' => 255]),
                            ]),

                        TextInput::make('code')
                            ->label(__('filament.combo.code'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->suffixAction(
                                Action::make('generateCode')
                                    ->icon('heroicon-o-arrow-path')
                                    ->tooltip(__('filament.combo.generate_code'))
                                    ->action(fn(callable $set, $get) => $set('code', Helper::generateComboCode($get('name'))))
                            )
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_length', ['min' => 3]),
                                'max' => __('common.error.max_length', ['max' => 50]),
                                'unique' => __('common.error.unique'),
                            ]),

                        Select::make('status')
                            ->label(__('filament.combo.status'))
                            ->options(StatusCombo::getOptions())
                            ->default(StatusCombo::UPCOMING)
                            ->disabled(fn($livewire) => ($livewire instanceof CreateRecord)),
                    ]),
                ]),

            Section::make(__('filament.combo.time_period'))
                ->schema([
                    Grid::make(2)->schema([

                        DateTimePicker::make('start_date')
                            ->label(__('filament.combo.start_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->default(now())
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'date' => __('common.error.invalid_date'),
                            ]),

                        DateTimePicker::make('end_date')
                            ->label(__('filament.combo.end_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->minDate(fn(Get $get) => $get('start_date'))
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'after_or_equal' => __('common.error.after_or_equal', ['field' => __('filament.combo.start_date')]),
                            ]),
                    ]),
                ]),

            Section::make(__('filament.combo.products'))
                ->collapsible()
                ->collapsed()
                ->schema([
                    Repeater::make('productsPivot')
                        ->label(__('filament.combo.product_list'))
                        ->relationship('productsPivot',)
                        ->schema([
                            Select::make('product_id')
                                ->label(__('filament.combo.product'))
                                ->relationship(
                                    name: 'product',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn($query) =>
                                    $query->where('organization_id', Auth::user()->organization_id)
                                )
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live(debounce: 500)
                                ->columnSpan(2)

                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (!$state) {
                                        // Reset các giá trị khi sản phẩm bị xóa chọn
                                        $set('price_origin', null);
                                        $set('price', null);
                                        $set('quantity', 1);
                                        return;
                                    }

                                    $product = Product::find($state);

                                    if ($product) {
                                        $set('price_origin', $product->sale_price);
                                        $set('price', $product->sale_price);
                                    } else {
                                        $set('price_origin', null);
                                        $set('price', null);
                                    }
                                })

                                ->disableOptionWhen(
                                    fn($value, $state, $get) =>
                                    collect($get('../../productsPivot'))
                                        ->pluck('product_id')
                                        ->contains($value) && $value != $state
                                )
                                ->validationMessages([
                                    'required' => __('common.error.required'),
                                    'exists' => __('common.error.not_exist'),
                                ]),

                            TextInput::make('quantity')
                                ->label(__('filament.combo.quantity'))
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->required()
                                ->reactive()
                                ->rule(function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $productId = $get('product_id');
                                        if (! $productId || empty($value)) return;

                                        $product = Product::find($productId);

                                        if ($product && $value > $product->quantity) {
                                            $fail(__('common.error.max.numeric', ['max' => $product->quantity]));
                                        }
                                    };
                                })
                                ->validationMessages([
                                    'required' => __('common.error.required'),
                                    'numeric' => __('common.error.numeric'),
                                    'min' => __('common.error.min.numeric', ['min' => 1]),
                                ]),

                            TextInput::make('price_origin')
                                ->label(__('filament.product.sale_price'))
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->prefix('₫'),

                            TextInput::make('price')
                                ->label(__('filament.combo.price_in_combo'))
                                ->numeric()
                                ->prefix('₫')
                                ->minValue(0)
                                ->required()
                                ->reactive()
                                ->rule(function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $priceOrigin = $get('price_origin');
                                        if ($priceOrigin == null || $value == null) return;
                                        if ($value > $priceOrigin) {
                                            $fail(__('common.error.max.numeric', [
                                                'max' => number_format($priceOrigin, 0, ',', '.') . ' ₫'
                                            ]));
                                        }
                                    };
                                })
                                ->validationMessages([
                                    'required' => __('common.error.required'),
                                    'numeric' => __('common.error.numeric'),
                                    'min' => __('common.error.min.numeric', ['min' => 0]),
                                ]),

                        ])
                        ->columns(3)
                        ->minItems(2)
                        ->maxItems(20)
                        ->addActionLabel(__('filament.combo.add_product'))
                        ->collapsible()
                        ->validationMessages([
                            'required' => __('common.error.required'),
                            'min' => __('common.error.min.array', ['min' => 2]),
                            'max' => __('common.error.max.array', ['max' => 20]),
                        ]),
                ]),

            Section::make(__('filament.combo.summary'))
                ->schema([
                    Grid::make(4)->schema([
                        Placeholder::make('total_product')
                            ->label(__('filament.combo.total_product'))
                            ->content(fn($record) => $record?->total_product ?? '—'),

                        Placeholder::make('total_cost')
                            ->label(__('filament.combo.total_cost'))
                            ->content(
                                fn($record) =>
                                $record ? number_format($record->total_cost, 0, ',', '.') . ' ₫' : '—'
                            ),

                        Placeholder::make('total_combo_price')
                            ->label(__('filament.combo.total_combo_price'))
                            ->content(
                                fn($record) =>
                                $record ? number_format($record->total_combo_price, 0, ',', '.') . ' ₫' : '—'
                            ),

                        Placeholder::make('discount_percentage')
                            ->label(__('filament.combo.discount'))
                            ->content(
                                fn($record) =>
                                $record ? number_format($record->discount_percentage, 1) . '%' : '—'
                            ),
                    ]),
                ])
                ->collapsible(),
        ]);
    }
}

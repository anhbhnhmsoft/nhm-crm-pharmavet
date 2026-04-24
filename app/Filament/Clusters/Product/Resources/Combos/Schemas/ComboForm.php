<?php

namespace App\Filament\Clusters\Product\Resources\Combos\Schemas;

use App\Common\Constants\Product\StatusCombo;
use App\Models\Combo;
use App\Models\Product;
use App\Utils\Helper;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ComboForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('filament.combo.basic_info'))
                ->description(__('filament.combo.basic_info_description'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label(__('filament.combo.name'))
                            ->validationAttribute(__('filament.combo.name'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->extraInputAttributes(['required' => false, 'minlength' => null, 'maxlength' => null])
                            ->live(debounce: 1000, onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('code', Helper::generateComboCode($state)))
                            ->validationMessages([
                                'required' => self::requiredMessage(__('filament.combo.name')),
                                'min' => self::stringMinMessage(__('filament.combo.name'), 3),
                                'max' => self::stringMaxMessage(__('filament.combo.name'), 255),
                            ]),

                        TextInput::make('code')
                            ->label(__('filament.combo.code'))
                            ->validationAttribute(__('filament.combo.code'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->extraInputAttributes(['required' => false, 'minlength' => null, 'maxlength' => null])
                            ->suffixAction(
                                Action::make('generateCode')
                                    ->icon('heroicon-o-arrow-path')
                                    ->tooltip(__('filament.combo.generate_code'))
                                    ->action(fn (callable $set, $get) => $set('code', Helper::generateComboCode($get('name'))))
                            )
                            ->validationMessages([
                                'required' => self::requiredMessage(__('filament.combo.code')),
                                'min' => self::stringMinMessage(__('filament.combo.code'), 3),
                                'max' => self::stringMaxMessage(__('filament.combo.code'), 50),
                                'unique' => self::uniqueMessage(__('filament.combo.code')),
                            ]),

                        Select::make('status')
                            ->label(__('filament.combo.status'))
                            ->options(StatusCombo::getOptions())
                            ->default(StatusCombo::UPCOMING->value)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(__('filament.combo.status_helper')),
                    ]),
                ]),

            Section::make(__('filament.combo.time_period'))
                ->description(__('filament.combo.time_period_description'))
                ->schema([
                    Grid::make(2)->schema([
                        DateTimePicker::make('start_date')
                            ->label(__('filament.combo.start_date'))
                            ->validationAttribute(__('filament.combo.start_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->required()
                            ->default(now())
                            ->live()
                            ->validationMessages([
                                'required' => self::requiredMessage(__('filament.combo.start_date')),
                                'date' => self::dateMessage(__('filament.combo.start_date')),
                            ]),

                        DateTimePicker::make('end_date')
                            ->label(__('filament.combo.end_date'))
                            ->validationAttribute(__('filament.combo.end_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->required()
                            ->afterOrEqual('start_date')
                            ->live()
                            ->validationMessages([
                                'required' => self::requiredMessage(__('filament.combo.end_date')),
                                'date' => self::dateMessage(__('filament.combo.end_date')),
                                'after_or_equal' => self::afterOrEqualMessage(__('filament.combo.end_date'), __('filament.combo.start_date')),
                            ]),
                    ]),

                    Placeholder::make('applicability_status')
                        ->label(__('filament.combo.validity_status'))
                        ->content(fn (Get $get) => self::resolveValidityMessage($get('start_date'), $get('end_date'))),
                ]),

            Section::make(__('filament.combo.products'))
                ->description(__('filament.combo.products_description'))
                ->schema([
                    Repeater::make('productsPivot')
                        ->label(__('filament.combo.product_list'))
                        ->validationAttribute(__('filament.combo.product_list'))
                        ->relationship('productsPivot')
                        ->schema([
                            Select::make('product_id')
                                ->label(__('filament.combo.product'))
                                ->validationAttribute(__('filament.combo.product'))
                                ->options(fn (): array => self::getProductOptions())
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->columnSpan(2)
                                ->afterStateHydrated(function ($state, Set $set) {
                                    $productId = (int) ($state ?? 0);

                                    if ($productId <= 0) {
                                        return;
                                    }

                                    $product = Product::query()->find($productId);

                                    if ($product) {
                                        $set('price_origin', (float) $product->sale_price);
                                    }
                                })
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (! $state) {
                                        $set('price_origin', null);
                                        $set('price', null);
                                        $set('quantity', 1);

                                        return;
                                    }

                                    $product = Product::query()->find($state);

                                    if (! $product) {
                                        $set('price_origin', null);
                                        $set('price', null);

                                        return;
                                    }

                                    $set('price_origin', (float) $product->sale_price);
                                    $set('price', (float) $product->sale_price);
                                    $set('quantity', (int) ($product->pivot?->quantity ?? 1));
                                })
                                ->disableOptionWhen(
                                    fn ($value, $state, $get) => collect($get('../../productsPivot') ?? [])
                                        ->pluck('product_id')
                                        ->filter()
                                        ->contains(fn ($selected) => (string) $selected === (string) $value && (string) $state !== (string) $value)
                                )
                                ->validationMessages([
                                    'required' => self::requiredMessage(__('filament.combo.product')),
                                    'exists' => self::existsMessage(__('filament.combo.product')),
                                ]),

                            TextInput::make('quantity')
                                ->label(__('filament.combo.quantity'))
                                ->validationAttribute(__('filament.combo.quantity'))
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->required()
                                ->extraInputAttributes([
                                    'type' => 'text',
                                    'inputmode' => 'numeric',
                                    'required' => false,
                                    'min' => null,
                                    'max' => null,
                                    'step' => null,
                                ])
                                ->live()
                                ->rule(function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $productId = (int) ($get('product_id') ?? 0);

                                        if ($productId <= 0 || blank($value)) {
                                            return;
                                        }

                                        $product = Product::query()->find($productId);

                                        if ($product && (int) $value > (int) $product->quantity) {
                                            $fail(self::numericMaxMessage(__('filament.combo.quantity'), (float) $product->quantity));
                                        }
                                    };
                                })
                                ->validationMessages([
                                    'required' => self::requiredMessage(__('filament.combo.quantity')),
                                    'numeric' => self::numericMessage(__('filament.combo.quantity')),
                                    'min' => self::numericMinMessage(__('filament.combo.quantity'), 1),
                                ]),

                            TextInput::make('price_origin')
                                ->label(__('filament.product.sale_price'))
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->prefix('₫'),

                            TextInput::make('price')
                                ->label(__('filament.combo.price_in_combo'))
                                ->validationAttribute(__('filament.combo.price_in_combo'))
                                ->numeric()
                                ->prefix('₫')
                                ->minValue(0)
                                ->required()
                                ->extraInputAttributes([
                                    'type' => 'text',
                                    'inputmode' => 'decimal',
                                    'required' => false,
                                    'min' => null,
                                    'max' => null,
                                    'step' => null,
                                ])
                                ->live()
                                ->rule(function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $priceOrigin = $get('price_origin');

                                        if ($priceOrigin === null || $value === null) {
                                            return;
                                        }

                                        if ((float) $value > (float) $priceOrigin) {
                                            $fail(self::numericMaxMessage(
                                                __('filament.combo.price_in_combo'),
                                                number_format((float) $priceOrigin, 0, ',', '.') . ' ₫',
                                            ));
                                        }
                                    };
                                })
                                ->validationMessages([
                                    'required' => self::requiredMessage(__('filament.combo.price_in_combo')),
                                    'numeric' => self::numericMessage(__('filament.combo.price_in_combo')),
                                    'min' => self::numericMinMessage(__('filament.combo.price_in_combo'), 0),
                                ]),
                        ])
                        ->columns(3)
                        ->minItems(2)
                        ->maxItems(20)
                        ->addActionLabel(__('filament.combo.add_product'))
                        ->collapsible()
                        ->validationMessages([
                            'required' => self::requiredMessage(__('filament.combo.product_list')),
                            'min' => self::arrayMinMessage(__('filament.combo.product_list'), 2),
                            'max' => self::arrayMaxMessage(__('filament.combo.product_list'), 20),
                        ]),
                ]),

            Section::make(__('filament.combo.summary'))
                ->description(__('filament.combo.summary_description'))
                ->schema([
                    Grid::make(3)->schema([
                        Placeholder::make('total_product')
                            ->label(__('filament.combo.total_product'))
                            ->content(fn (Get $get, ?Combo $record) => self::resolveSummaryValue($get('productsPivot'), $record, 'total_product')),

                        Placeholder::make('total_cost')
                            ->label(__('filament.combo.total_cost'))
                            ->content(fn (Get $get, ?Combo $record) => self::formatCurrency(self::resolveSummaryValue($get('productsPivot'), $record, 'total_cost'))),

                        Placeholder::make('total_original_price')
                            ->label(__('filament.combo.total_original_price'))
                            ->content(fn (Get $get, ?Combo $record) => self::formatCurrency(self::resolveSummaryValue($get('productsPivot'), $record, 'total_original_price'))),

                        Placeholder::make('total_combo_price')
                            ->label(__('filament.combo.total_combo_price'))
                            ->content(fn (Get $get, ?Combo $record) => self::formatCurrency(self::resolveSummaryValue($get('productsPivot'), $record, 'total_combo_price'))),

                        Placeholder::make('savings_amount')
                            ->label(__('filament.combo.savings'))
                            ->content(fn (Get $get, ?Combo $record) => self::formatCurrency(self::resolveSummaryValue($get('productsPivot'), $record, 'savings_amount'))),

                        Placeholder::make('discount_percentage')
                            ->label(__('filament.combo.discount'))
                            ->content(fn (Get $get, ?Combo $record) => self::formatPercentage(self::resolveSummaryValue($get('productsPivot'), $record, 'savings_percentage'))),
                    ]),
                ])
                ->collapsible(),
        ]);
    }

    protected static function getProductOptions(): array
    {
        return Product::query()
            ->where('organization_id', Auth::user()->organization_id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function resolveSummaryValue(mixed $state, ?Combo $record, string $key): float|int
    {
        $summary = self::calculateSummaryFromState($state);

        if ($summary !== []) {
            return $summary[$key] ?? 0;
        }

        if (! $record) {
            return 0;
        }

        $record->loadMissing('productsPivot.product');

        return match ($key) {
            'total_product' => (int) $record->total_product,
            'total_cost' => (float) $record->total_cost,
            'total_original_price' => (float) $record->original_sale_total,
            'total_combo_price' => (float) $record->total_combo_price,
            'savings_amount' => max((float) $record->original_sale_total - (float) $record->total_combo_price, 0),
            'savings_percentage' => (float) $record->discount_percentage,
            default => 0,
        };
    }

    protected static function calculateSummaryFromState(mixed $state): array
    {
        $items = is_array($state) ? $state : [];

        if ($items === []) {
            return [];
        }

        $totalProduct = 0;
        $totalCost = 0;
        $totalOriginalPrice = 0;
        $totalComboPrice = 0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $product = Product::query()->find($productId);

            if (! $product) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            $comboPrice = (float) ($item['price'] ?? 0);

            $totalProduct += $quantity;
            $totalCost += ((float) ($product->cost_price ?? 0)) * $quantity;
            $totalOriginalPrice += ((float) ($product->sale_price ?? 0)) * $quantity;
            $totalComboPrice += $comboPrice * $quantity;
        }

        $savingsAmount = max($totalOriginalPrice - $totalComboPrice, 0);
        $savingsPercentage = $totalOriginalPrice > 0
            ? round(($savingsAmount / $totalOriginalPrice) * 100, 2)
            : 0;

        return [
            'total_product' => $totalProduct,
            'total_cost' => $totalCost,
            'total_original_price' => $totalOriginalPrice,
            'total_combo_price' => $totalComboPrice,
            'savings_amount' => $savingsAmount,
            'savings_percentage' => $savingsPercentage,
        ];
    }

    protected static function resolveValidityMessage(mixed $startDate, mixed $endDate): string
    {
        if (blank($startDate) || blank($endDate)) {
            return __('filament.combo.validity_unknown');
        }

        try {
            $now = now();
            $start = \Illuminate\Support\Carbon::parse($startDate);
            $end = \Illuminate\Support\Carbon::parse($endDate);
        } catch (\Throwable) {
            return __('filament.combo.validity_unknown');
        }

        if ($now->lt($start)) {
            return __('filament.combo.validity_upcoming_message');
        }

        if ($now->gt($end)) {
            return __('filament.combo.validity_expired_message');
        }

        return __('filament.combo.validity_active_message');
    }

    protected static function formatCurrency(float|int $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' ₫';
    }

    protected static function formatPercentage(float|int $value): string
    {
        return number_format((float) $value, 1) . '%';
    }

    protected static function requiredMessage(string $attribute): string
    {
        return __('validation.required', ['attribute' => $attribute]);
    }

    protected static function uniqueMessage(string $attribute): string
    {
        return __('validation.unique', ['attribute' => $attribute]);
    }

    protected static function existsMessage(string $attribute): string
    {
        return __('validation.exists', ['attribute' => $attribute]);
    }

    protected static function dateMessage(string $attribute): string
    {
        return __('validation.date', ['attribute' => $attribute]);
    }

    protected static function numericMessage(string $attribute): string
    {
        return __('validation.numeric', ['attribute' => $attribute]);
    }

    protected static function stringMinMessage(string $attribute, int $min): string
    {
        return __('validation.min.string', ['attribute' => $attribute, 'min' => $min]);
    }

    protected static function stringMaxMessage(string $attribute, int $max): string
    {
        return __('validation.max.string', ['attribute' => $attribute, 'max' => $max]);
    }

    protected static function numericMinMessage(string $attribute, int|float $min): string
    {
        return __('validation.min.numeric', ['attribute' => $attribute, 'min' => $min]);
    }

    protected static function numericMaxMessage(string $attribute, int|float|string $max): string
    {
        return __('validation.max.numeric', ['attribute' => $attribute, 'max' => $max]);
    }

    protected static function arrayMinMessage(string $attribute, int $min): string
    {
        return __('validation.min.array', ['attribute' => $attribute, 'min' => $min]);
    }

    protected static function arrayMaxMessage(string $attribute, int $max): string
    {
        return __('validation.max.array', ['attribute' => $attribute, 'max' => $max]);
    }

    protected static function afterOrEqualMessage(string $attribute, string $date): string
    {
        return __('validation.after_or_equal', ['attribute' => $attribute, 'date' => $date]);
    }
}

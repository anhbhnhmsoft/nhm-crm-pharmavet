<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Schemas;

use App\Filament\Clusters\Telesale\Resources\CustomerOperations\Actions\InteractionStepActions;
use App\Models\District;
use App\Models\Province;
use App\Models\Warehouse;
use App\Models\Ward;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use App\Models\Organization;
use App\Models\Product;
use App\Common\Constants\User\UserRole;
use Illuminate\Support\Facades\Auth;

class TelesaleOperationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::getComponents());
    }

    protected static function normalizePositiveNumber(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (float) $value);
    }

    protected static function normalizePercentage(mixed $value): float
    {
        return min(100, self::normalizePositiveNumber($value));
    }

    protected static function resolveOrganizationId(Get $get, string $path = 'organization_id'): ?int
    {
        $user = Auth::user();

        if ((int) ($user?->role ?? 0) !== UserRole::SUPER_ADMIN->value) {
            return (int) ($user?->organization_id ?? 0) ?: null;
        }

        return (int) ($get($path) ?? 0) ?: null;
    }

    protected static function getBusinessProductOptions(?int $organizationId, ?int $selectedProductId = null): array
    {
        if (($organizationId ?? 0) <= 0 && ($selectedProductId ?? 0) <= 0) {
            return [];
        }

        return Product::query()
            ->where(function ($query) use ($organizationId, $selectedProductId): void {
                if (($organizationId ?? 0) > 0) {
                    $query->where('organization_id', $organizationId)
                        ->where('is_business_product', true);
                }

                if (($selectedProductId ?? 0) > 0) {
                    if (($organizationId ?? 0) > 0) {
                        $query->orWhereKey($selectedProductId);

                        return;
                    }

                    $query->whereKey($selectedProductId);
                }
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getComponents(): array
    {
        $today = now()->startOfDay();
        $minimumBirthday = $today->copy()->subYears(100);

        $calculateTotal = function (Get $get, Set $set) {
            $items = $get('order_items') ?? [];
            $subtotal = collect($items)->sum(function (array $item): float {
                return self::normalizePositiveNumber($item['quantity'] ?? 0)
                    * self::normalizePositiveNumber($item['price'] ?? 0);
            });

            $shippingFee = self::normalizePositiveNumber($get('shipping_fee'));
            $codFee = self::normalizePositiveNumber($get('cod_fee'));
            $deposit = self::normalizePositiveNumber($get('deposit'));
            $discount = self::normalizePositiveNumber($get('discount'));
            $ck1 = self::normalizePercentage($get('ck1'));
            $ck2 = self::normalizePercentage($get('ck2'));

            // Calculate discounts
            $discountCk1 = $subtotal * ($ck1 / 100);
            $discountCk2 = ($subtotal - $discountCk1) * ($ck2 / 100);

            $total = $subtotal - $discount - $discountCk1 - $discountCk2 + $shippingFee + $codFee - $deposit;

            $set('total_amount', max(0, $total));
        };

        return [
                Group::make()
                    ->schema([
                        Section::make(__('telesale.form.customer_info'))
                            ->description(__('telesale.form.customer_info_desc'))
                            ->schema([
                                Grid::make()->schema([
                                    TextInput::make('username')
                                        ->label(__('telesale.form.full_name'))
                                        ->placeholder(__('telesale.form.full_name_placeholder'))
                                        ->required()
                                        ->extraInputAttributes(['required' => false])
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                        ]),
                                    TextInput::make('phone')
                                        ->label(__('telesale.form.phone_number'))
                                        ->placeholder(__('telesale.form.phone_placeholder'))
                                        ->tel()
                                        ->maxLength(15)
                                        ->required()
                                        ->rules([
                                            'regex:/^(0|(\+84))[35789][0-9]{8}$/',
                                        ])
                                        ->extraInputAttributes([
                                            'required' => false,
                                            'type' => 'text',
                                            'inputmode' => 'tel',
                                        ])
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                            'regex' => __('common.error.phone_invalid'),
                                            'max' => __('common.error.max_length', ['max' => 15]),
                                        ]),
                                    TextInput::make('email')
                                        ->label(__('telesale.form.email'))
                                        ->placeholder(__('telesale.form.email_placeholder'))
                                        ->email()
                                        ->extraInputAttributes([
                                            'required' => false,
                                            'type' => 'text',
                                        ])
                                        ->validationMessages([
                                            'email' => __('common.error.email'),
                                        ]),
                                    DatePicker::make('birthday')
                                        ->label(__('telesale.form.birthday'))
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->rules(['nullable', 'date'])
                                        ->minDate($minimumBirthday)
                                        ->maxDate($today)
                                        ->afterOrEqual($minimumBirthday->toDateString())
                                        ->beforeOrEqual($today->toDateString())
                                        ->extraInputAttributes(['required' => false])
                                        ->validationMessages([
                                            'date' => __('validation.date', ['attribute' => __('telesale.form.birthday')]),
                                            'after_or_equal' => __('common.error.date_after', ['date' => $minimumBirthday->format('d/m/Y')]),
                                            'before_or_equal' => __('common.error.date_before', ['date' => $today->format('d/m/Y')]),
                                        ]),
                                    Textarea::make('address')
                                        ->label(__('telesale.form.address'))
                                        ->placeholder(__('telesale.form.address_placeholder')),
                                    Textarea::make('note_temp')
                                        ->label(__('telesale.form.note_temp')),
                                    Select::make('organization_id')
                                        ->label(__('telesale.form.organization'))
                                        ->options(Organization::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->live()
                                        ->visible(fn() => Auth::user()->role === UserRole::SUPER_ADMIN->value)
                                        ->default(Auth::user()->organization_id)
                                        ->afterStateUpdated(fn(Set $set) => $set('product_id', null))
                                        ->required()
                                        ->extraInputAttributes(['required' => false])
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                        ]),

                                    Select::make('product_id')
                                        ->label(__('telesale.form.product'))
                                        ->options(fn(Get $get): array => self::getBusinessProductOptions(
                                            self::resolveOrganizationId($get),
                                            (int) ($get('product_id') ?? 0),
                                        ))
                                        ->required()
                                        ->extraInputAttributes(['required' => false])
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                        ]),
                                ]),
                            ])
                            ->collapsible()
                            ->collapsed(false)
                            ->columnSpanFull(),

                        // Section::make(__('telesale.form.operation_result'))
                        //     ->description(__('telesale.form.operation_result_desc'))
                        //     ->schema([
                        //         Textarea::make('note')
                        //             ->label(__('telesale.form.feedback_note'))
                        //             ->placeholder(__('telesale.form.feedback_placeholder')),
                        //         DateTimePicker::make('next_action_at')
                        //             ->label(__('telesale.form.schedule_callback'))
                        //             ->native(false),
                        //     ])->columnSpanFull(),
                    ])
                    ->columnSpan([
                        'xl' => 2,
                        'md' => 6,
                        'default' => 3,
                    ]),
                Group::make()
                    ->schema([
                        Tabs::make(__('telesale.form.operation_tabs'))
                            ->tabs([
                                Tab::make(__('telesale.form.order_entry'))
                                    ->icon('heroicon-o-shopping-cart')
                                    ->schema([
                                        Section::make(__('telesale.form.product_payment'))
                                            ->schema([
                                                Select::make('warehouse_id')
                                                    ->label(__('order.form.warehouse'))
                                                    ->options(Warehouse::query()->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->required()
                                                    ->extraInputAttributes(['required' => false])
                                                    ->validationMessages([
                                                        'required' => __('common.error.required'),
                                                    ]),
                                                Repeater::make('order_items')
                                                    ->label(__('telesale.form.product_list'))
                                                    ->schema([
                                                        Grid::make()->schema([
                                                            Select::make('product_id')
                                                                ->label(__('telesale.form.product'))
                                                                ->searchable()
                                                                ->options(fn(Get $get): array => self::getBusinessProductOptions(
                                                                    self::resolveOrganizationId($get, '../../organization_id'),
                                                                    (int) ($get('product_id') ?? 0),
                                                                ))
                                                                ->live()
                                                                ->required()
                                                                ->afterStateUpdated(function ($state, Set $set) {
                                                                    $product = Product::find($state);
                                                                    $set('price', $product?->sale_price ?? 0);
                                                                    $set('quantity', 1);
                                                                })
                                                                ->extraInputAttributes(['required' => false])
                                                                ->validationMessages([
                                                                    'required' => __('common.error.required'),
                                                                ]),
                                                            TextInput::make('quantity')
                                                                ->label(__('telesale.form.quantity'))
                                                                ->integer()
                                                                ->default(1)
                                                                ->minValue(1)
                                                                ->live()
                                                                ->required()
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
                                                                    'integer' => __('common.error.numeric'),
                                                                    'min' => __('common.error.min_value', ['min' => 1]),
                                                                ]),
                                                            TextInput::make('price')
                                                                ->label(__('telesale.form.unit_price'))
                                                                ->numeric()
                                                                ->rule('decimal:0,2')
                                                                ->minValue(0)
                                                                ->extraInputAttributes([
                                                                    'type' => 'text',
                                                                    'inputmode' => 'decimal',
                                                                    'required' => false,
                                                                    'min' => null,
                                                                    'max' => null,
                                                                    'step' => null,
                                                                ])
                                                                ->live()
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required' => __('common.error.required'),
                                                                    'numeric' => __('common.error.numeric'),
                                                                    'decimal' => __('common.error.numeric'),
                                                                    'min' => __('common.error.min_value', ['min' => 0]),
                                                                ]),
                                                        ]),
                                                    ])
                                                    ->defaultItems(1)
                                                    ->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                Grid::make()->schema([
                                                    TextInput::make('cod_fee')
                                                        ->label(__('telesale.form.cod_fee'))
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->extraInputAttributes([
                                                            'type' => 'text',
                                                            'inputmode' => 'decimal',
                                                            'required' => false,
                                                            'min' => null,
                                                            'max' => null,
                                                            'step' => null,
                                                        ])
                                                        ->live()
                                                        ->validationMessages([
                                                            'numeric' => __('common.error.numeric'),
                                                            'min' => __('common.error.min_value', ['min' => 0]),
                                                        ])
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('deposit')
                                                        ->label(__('telesale.form.deposit'))
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->extraInputAttributes([
                                                            'type' => 'text',
                                                            'inputmode' => 'decimal',
                                                            'required' => false,
                                                            'min' => null,
                                                            'max' => null,
                                                            'step' => null,
                                                        ])
                                                        ->live()
                                                        ->validationMessages([
                                                            'numeric' => __('common.error.numeric'),
                                                            'min' => __('common.error.min_value', ['min' => 0]),
                                                        ])
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('discount')
                                                        ->label(__('telesale.form.discount_amount'))
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->extraInputAttributes([
                                                            'type' => 'text',
                                                            'inputmode' => 'decimal',
                                                            'required' => false,
                                                            'min' => null,
                                                            'max' => null,
                                                            'step' => null,
                                                        ])
                                                        ->live()
                                                        ->validationMessages([
                                                            'numeric' => __('common.error.numeric'),
                                                            'min' => __('common.error.min_value', ['min' => 0]),
                                                        ])
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('ck1')
                                                        ->label(__('telesale.form.ck1'))
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->maxValue(100)
                                                        ->extraInputAttributes([
                                                            'type' => 'text',
                                                            'inputmode' => 'decimal',
                                                            'required' => false,
                                                            'min' => null,
                                                            'max' => null,
                                                            'step' => null,
                                                        ])
                                                        ->suffix('%')
                                                        ->live()
                                                        ->validationMessages([
                                                            'numeric' => __('common.error.numeric'),
                                                            'min' => __('common.error.min_value', ['min' => 0]),
                                                            'max' => __('common.error.max_value', ['max' => 100]),
                                                        ])
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('ck2')
                                                        ->label(__('telesale.form.ck2'))
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->maxValue(100)
                                                        ->extraInputAttributes([
                                                            'type' => 'text',
                                                            'inputmode' => 'decimal',
                                                            'required' => false,
                                                            'min' => null,
                                                            'max' => null,
                                                            'step' => null,
                                                        ])
                                                        ->suffix('%')
                                                        ->live()
                                                        ->validationMessages([
                                                            'numeric' => __('common.error.numeric'),
                                                            'min' => __('common.error.min_value', ['min' => 0]),
                                                            'max' => __('common.error.max_value', ['max' => 100]),
                                                        ])
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('total_amount')
                                                        ->label(__('telesale.form.total_amount'))
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->extraInputAttributes([
                                                            'type' => 'text',
                                                            'inputmode' => 'decimal',
                                                            'min' => null,
                                                            'max' => null,
                                                            'step' => null,
                                                        ])
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->validationMessages([
                                                            'numeric' => __('common.error.numeric'),
                                                            'min' => __('common.error.min_value', ['min' => 0]),
                                                        ]),
                                                ]),
                                            ]),
                                    ]),
                                Tab::make(__('telesale.form.interaction_history'))
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        Section::make(__('telesale.form.call_history'))
                                            ->schema([
                                                ViewField::make('interactions_timeline')
                                                    ->label('')
                                                    ->view('filament.components.customer-interactions-timeline')
                                                    ->columnSpanFull(),
                                            ]),
                                        Section::make(__('telesale.form.add_new_note'))
                                            ->schema([
                                                InteractionStepActions::reasonField('interaction_reason')
                                                    ->native(false)
                                                    ->required(fn(Get $get, $livewire) => $livewire instanceof EditRecord && (
                                                        filled($get('interaction_note')) ||
                                                        filled($get('interaction_next_action_at'))
                                                    )),
                                                InteractionStepActions::nextActionField('interaction_reason', 'interaction_next_action_at'),
                                                InteractionStepActions::noteField('interaction_note'),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpan([
                        'xl' => 4,
                        'md' => 6,
                        'default' => 3,
                    ]),
        ];
    }
}

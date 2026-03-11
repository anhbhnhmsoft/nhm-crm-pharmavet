<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Schemas;

use App\Models\District;
use App\Models\Province;
use App\Models\Ward;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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
        $calculateTotal = function (Get $get, Set $set) {
            $items = $get('order_items') ?? [];
            $subtotal = collect($items)->sum(fn($item) => ($item['quantity'] ?? 0) * ($item['price'] ?? 0));

            $shippingFee = (float) ($get('shipping_fee') ?? 0);
            $codFee = (float) ($get('cod_fee') ?? 0);
            $deposit = (float) ($get('deposit') ?? 0);
            $discount = (float) ($get('discount') ?? 0);
            $ck1 = (float) ($get('ck1') ?? 0);
            $ck2 = (float) ($get('ck2') ?? 0);

            // Calculate discounts
            $discountCk1 = $subtotal * ($ck1 / 100);
            $discountCk2 = ($subtotal - $discountCk1) * ($ck2 / 100);

            $total = $subtotal - $discount - $discountCk1 - $discountCk2 + $shippingFee + $codFee - $deposit;

            $set('total_amount', max(0, $total));
        };

        return $schema
            ->components([
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
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                        ]),
                                    TextInput::make('phone')
                                        ->label(__('telesale.form.phone_number'))
                                        ->placeholder(__('telesale.form.phone_placeholder'))
                                        ->tel()
                                        ->required()
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                        ]),
                                    TextInput::make('email')
                                        ->label(__('telesale.form.email'))
                                        ->placeholder(__('telesale.form.email_placeholder'))
                                        ->email()
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                        ]),
                                    DatePicker::make('birthday')
                                        ->label(__('telesale.form.birthday')),
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
                                        ->required(),

                                    Select::make('product_id')
                                        ->label(__('telesale.form.product'))
                                        ->options(function (Get $get) {
                                            $user = Auth::user();
                                            $orgId = $user->role === UserRole::SUPER_ADMIN->value
                                                ? $get('organization_id')
                                                : $user->organization_id;

                                            if (!$orgId) {
                                                return [];
                                            }

                                            return Product::where('organization_id', $orgId)
                                                ->where('is_business_product', true)
                                                ->pluck('name', 'id');
                                        })
                                        ->required()
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
                        Section::make(__('telesale.form.shipping_info'))
                            ->schema([
                                Select::make('province_id')
                                    ->label(__('warehouse.order.form.province'))
                                    ->options(Province::all()->pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('district_id', null);
                                        $set('ward_id', null);
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                Select::make('district_id')
                                    ->label(__('warehouse.order.form.district'))
                                    ->options(fn($get) => District::where('province_id', $get('province_id'))->pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('ward_id', null);
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                Select::make('ward_id')
                                    ->label(__('warehouse.order.form.ward'))
                                    ->options(fn($get) => Ward::where('district_id', $get('district_id'))->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()->validationMessages([
                                            'required' => __('common.error.required'),
                                        ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                // Group::make()
                //     ->schema([
                //         Tabs::make('NghiepVu')
                //             ->tabs([
                //                 Tab::make(__('telesale.form.order_entry'))
                //                     ->icon('heroicon-o-shopping-cart')
                //                     ->schema([
                //                         Section::make(__('telesale.form.shipping_info'))
                //                             ->schema([
                //                                 Grid::make()->schema([
                //                                     Select::make('province_id')
                //                                         ->label(__('telesale.form.province'))
                //                                         ->searchable()
                //                                         ->options(Province::all()->pluck('full_name', 'id'))
                //                                         ->native(false)
                //                                         ->required()
                //                                         ->validationMessages([
                //                                             'required' => __('common.error.required'),
                //                                         ]),
                //                                     Select::make('district_id')
                //                                         ->label(__('telesale.form.district'))
                //                                         ->searchable()
                //                                         ->native(false)
                //                                         ->options(fn($get) => District::all()->where('province_id', $get('province_id'))->pluck('full_name', 'id'))
                //                                         ->required()
                //                                         ->validationMessages([
                //                                             'required' => __('common.error.required'),
                //                                         ]),
                //                                     Select::make('ward_id')
                //                                         ->label(__('telesale.form.ward'))
                //                                         ->searchable()
                //                                         ->native(false)
                //                                         ->options(fn($get) => Ward::all()->where('district_id', $get('district_id'))->pluck('full_name', 'id'))
                //                                         ->required()
                //                                         ->validationMessages([
                //                                             'required' => __('common.error.required'),
                //                                         ]),
                //                                     LocationPicker::make('shipping_address')
                //                                         ->label(__('telesale.form.detailed_address'))
                //                                         ->required()
                //                                         ->validationMessages([
                //                                             'required' => __('common.error.required'),
                //                                         ])
                //                                         ->columnSpanFull(),
                //                                 ]),
                //                             ])
                //                             ->collapsible(),

                //                         Section::make(__('telesale.form.product_payment'))
                //                             ->schema([
                //                                 Repeater::make('order_items')
                //                                     ->label(__('telesale.form.product_list'))
                //                                     ->schema([
                //                                         Grid::make()->schema([
                //                                             Select::make('product_id')
                //                                                 ->label(__('telesale.form.product'))
                //                                                 ->searchable()
                //                                                 ->options(Product::all()->where('is_business_product', true)->where( 'organization_id', Auth::user()->id)->pluck('name', 'id'))
                //                                                 ->native(true)
                //                                                 ->live()
                //                                                 ->required()
                //                                                 ->validationMessages([
                //                                                     'required' => __('common.error.required'),
                //                                                 ]),
                //                                             TextInput::make('quantity')
                //                                                 ->label(__('telesale.form.quantity'))
                //                                                 ->numeric()
                //                                                 ->default(1)
                //                                                 ->live()
                //                                                 ->required()
                //                                                 ->validationMessages([
                //                                                     'required' => __('common.error.required'),
                //                                                 ]),
                //                                             TextInput::make('price')
                //                                                 ->label(__('telesale.form.unit_price'))
                //                                                 ->numeric()
                //                                                 ->disabled()
                //                                                 ->live()
                //                                                 ->required()
                //                                                 ->validationMessages([
                //                                                     'required' => __('common.error.required'),
                //                                                 ]),
                //                                         ]),
                //                                     ])
                //                                     ->defaultItems(1)
                //                                     ->live()
                //                                     ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),

                //                                 Grid::make()->schema([
                //                                     Select::make('shipping_method')
                //                                         ->label(__('telesale.form.shipping_provider'))
                //                                         ->options(ShippingMethod::getOptions())
                //                                         ->native(false)
                //                                         ->live()
                //                                         ->required()
                //                                         ->validationMessages([
                //                                             'required' => __('common.error.required'),
                //                                         ]),

                //                                     Select::make('required_note')
                //                                         ->label(__('telesale.form.ghn_check_goods'))
                //                                         ->options(RequiredNote::getOptions())
                //                                         ->visible(fn(Get $get) => $get('shipping_method') === 'ghn')
                //                                         ->native(false)
                //                                         ->required()
                //                                         ->validationMessages([
                //                                             'required' => __('common.error.required'),
                //                                         ]),

                //                                     TextInput::make('shipping_fee')
                //                                         ->label(__('telesale.form.shipping_fee'))
                //                                         ->numeric()
                //                                         ->prefix('₫')
                //                                         ->live()
                //                                         ->required()
                //                                         ->validationMessages([
                //                                             'required' => __('common.error.required'),
                //                                         ])
                //                                         ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),

                //                                     TextInput::make('cod_fee')
                //                                         ->label(__('telesale.form.cod_fee'))
                //                                         ->numeric()
                //                                         ->prefix('₫')
                //                                         ->live()
                //                                         ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),

                //                                     TextInput::make('deposit')
                //                                         ->label(__('telesale.form.deposit'))
                //                                         ->numeric()
                //                                         ->prefix('₫')
                //                                         ->live()
                //                                         ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),

                //                                     Grid::make()->schema([
                //                                         TextInput::make('discount')
                //                                             ->label(__('telesale.form.discount_amount'))
                //                                             ->numeric()
                //                                             ->prefix('₫')
                //                                             ->live()
                //                                             ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                //                                         TextInput::make('ck1')
                //                                             ->label('CK 1 (%)')
                //                                             ->numeric()
                //                                             ->suffix('%')
                //                                             ->live()
                //                                             ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                //                                         TextInput::make('ck2')
                //                                             ->label('CK 2 (%)')
                //                                             ->numeric()
                //                                             ->suffix('%')
                //                                             ->live()
                //                                             ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                //                                     ]),

                //                                     TextInput::make('total_amount')
                //                                         ->label(__('telesale.form.total_amount'))
                //                                         ->numeric()
                //                                         ->disabled()
                //                                         ->prefix('₫')
                //                                         ->dehydrated()
                //                                         ->extraAttributes(['class' => 'font-bold text-xl text-primary-600']),
                //                                 ]),
                //                             ])
                //                             ->collapsible(),
                //                     ]),

                //                 Tab::make(__('telesale.form.interaction_history'))
                //                     ->icon('heroicon-o-clock')
                //                     ->schema([
                //                         Section::make(__('telesale.form.call_history'))
                //                             ->schema([
                //                                 \Filament\Forms\Components\ViewField::make('interactions_timeline')
                //                                     ->label('')
                //                                     ->view('filament.components.customer-interactions-timeline')
                //                                     ->columnSpanFull(),
                //                             ])
                //                             ->collapsible()
                //                             ->collapsed(false),

                //                         Section::make(__('telesale.form.add_new_note'))
                //                             ->schema([
                //                                 Grid::make()->schema([
                //                                     Select::make('new_interaction_status')
                //                                         ->label(__('telesale.form.result'))
                //                                         ->options(InteractionStatus::options())
                //                                         ->native(false)
                //                                         ->required()
                //                                         ->validationMessages([
                //                                             'required' => __('common.error.required'),
                //                                         ]),
                //                                 ]),
                //                                 Textarea::make('new_interaction_content')
                //                                     ->label(__('telesale.form.content'))
                //                                     ->placeholder(__('telesale.form.content_placeholder'))
                //                                     ->rows(3)
                //                                     ->columnSpanFull(),
                //                             ])
                //                             ->collapsible(),
                //                     ]),
                //             ])
                //             ->columnSpanFull(),
                //     ])
                //     ->columnSpan([
                //         'xl' => 4,
                //         'md' => 6,
                //         'default' => 3,
                //     ]),
            ]);
    }
}

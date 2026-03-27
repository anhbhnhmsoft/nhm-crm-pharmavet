<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Schemas;

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
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\User\UserRole;
use Illuminate\Support\Facades\Auth;

class TelesaleOperationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::getComponents());
    }

    public static function getComponents(): array
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
                                                                ->options(Product::query()->where('is_business_product', true)->pluck('name', 'id'))
                                                                ->live()
                                                                ->required()
                                                                ->afterStateUpdated(function ($state, Set $set) {
                                                                    $product = Product::find($state);
                                                                    $set('price', $product?->sale_price ?? 0);
                                                                    $set('quantity', 1);
                                                                }),
                                                            TextInput::make('quantity')
                                                                ->label(__('telesale.form.quantity'))
                                                                ->numeric()
                                                                ->default(1)
                                                                ->live()
                                                                ->required(),
                                                            TextInput::make('price')
                                                                ->label(__('telesale.form.unit_price'))
                                                                ->numeric()
                                                                ->live()
                                                                ->required(),
                                                        ]),
                                                    ])
                                                    ->defaultItems(1)
                                                    ->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                Grid::make()->schema([
                                                    TextInput::make('cod_fee')
                                                        ->label(__('telesale.form.cod_fee'))
                                                        ->numeric()
                                                        ->live()
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('deposit')
                                                        ->label(__('telesale.form.deposit'))
                                                        ->numeric()
                                                        ->live()
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('discount')
                                                        ->label(__('telesale.form.discount_amount'))
                                                        ->numeric()
                                                        ->live()
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('ck1')
                                                        ->label(__('telesale.form.ck1'))
                                                        ->numeric()
                                                        ->suffix('%')
                                                        ->live()
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('ck2')
                                                        ->label(__('telesale.form.ck2'))
                                                        ->numeric()
                                                        ->suffix('%')
                                                        ->live()
                                                        ->afterStateUpdated(fn(Get $get, Set $set) => $calculateTotal($get, $set)),
                                                    TextInput::make('total_amount')
                                                        ->label(__('telesale.form.total_amount'))
                                                        ->numeric()
                                                        ->disabled()
                                                        ->dehydrated(),
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
                                                Select::make('new_interaction_status')
                                                    ->label(__('telesale.form.result'))
                                                    ->options(InteractionStatus::options())
                                                    ->native(false),
                                                DateTimePicker::make('next_action_at')
                                                    ->label(__('telesale.form.schedule_callback'))
                                                    ->native(false),
                                                Textarea::make('new_interaction_content')
                                                    ->label(__('telesale.form.content'))
                                                    ->placeholder(__('telesale.form.content_placeholder'))
                                                    ->rows(3),
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

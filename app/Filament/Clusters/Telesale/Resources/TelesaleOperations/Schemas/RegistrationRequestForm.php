<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Schemas;

use App\Common\Constants\Interaction\InteractionStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use App\Common\Constants\Organization\ProductField;
use App\Models\Product;
use App\Common\Constants\User\UserRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class RegistrationRequestForm
{
    public static function getComponents(): array
    {
        return [
            Group::make()
                ->schema([
                    Section::make(__('telesale.registration_request.customer_info'))
                        ->description(__('telesale.registration_request.customer_info_desc'))
                        ->schema([
                            Grid::make()->schema([
                                TextInput::make('username')
                                    ->label(__('telesale.form.full_name'))
                                    ->prefixIcon('heroicon-o-user')
                                    ->disabled()
                                    ->columnSpan(1),
                                TextInput::make('phone')
                                    ->label(__('telesale.form.phone_number'))
                                    ->prefixIcon('heroicon-o-phone')
                                    ->copyable()
                                    ->disabled()
                                    ->columnSpan(1),
                                TextInput::make('email')
                                    ->label(__('telesale.form.email'))
                                    ->prefixIcon('heroicon-o-envelope')
                                    ->copyable()
                                    ->disabled()
                                    ->columnSpan(1),
                                TextInput::make('product_id_display')
                                    ->label(__('telesale.registration_request.industry'))
                                    ->prefixIcon('heroicon-o-briefcase')
                                    ->afterStateHydrated(function ($component, $record) {
                                        if (!$record) return;
                                        if ($record->product_field_id) {
                                            $component->state(ProductField::getLabel((int)$record->product_field_id));
                                        } else {
                                            // Fallback: Tìm trong note_temp nếu product_field_id bị null cho trường tợp "khác"
                                            preg_match('/Ngành hàng: (.*)/', $record->note_temp, $matches);
                                            $component->state($matches[1] ?? __('telesale.registration_request.custom_industry'));
                                        }
                                    })
                                    ->disabled()
                                    ->columnSpan(1),
                            ])->columns(2),
                        ])
                        ->collapsible()
                        ->columnSpanFull(),

                    Section::make(__('telesale.registration_request.original_request_details'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Placeholder::make('note_temp_display')
                                ->label('')
                                ->content(fn ($record) => $record ? new HtmlString(
                                    '<div class="p-4 bg-gray-50 border border-gray-100 rounded-xl">' .
                                    '<div class="text-sm leading-relaxed whitespace-pre-line text-gray-700">' .
                                    e($record->note_temp) .
                                    '</div></div>'
                                ) : ''),
                        ])
                        ->collapsible()
                        ->columnSpanFull(),
                ])
                ->columnSpan([
                    'xl' => 4,
                    'lg' => 4,
                    'md' => 6,
                    'default' => 3,
                ]),
                
            Group::make()
                ->schema([
                    Tabs::make(__('telesale.form.operation_tabs'))
                        ->tabs([
                            Tab::make(__('telesale.form.add_new_note'))
                                ->icon('heroicon-o-pencil-square')
                                ->schema([
                                    Section::make()
                                        ->compact()
                                        ->schema([
                                            Select::make('new_interaction_status')
                                                ->label(__('telesale.form.result'))
                                                ->options(InteractionStatus::options())
                                                ->native(false)
                                                ->required(),
                                            DateTimePicker::make('next_action_at')
                                                ->label(__('telesale.form.schedule_callback'))
                                                ->native(false),
                                            Textarea::make('new_interaction_content')
                                                ->label(__('telesale.form.content'))
                                                ->placeholder(__('telesale.form.content_placeholder'))
                                                ->rows(6)
                                                ->required(),
                                        ]),
                                ]),

                            Tab::make(__('telesale.form.interaction_history'))
                                ->icon('heroicon-o-clock')
                                ->schema([
                                    Section::make()
                                        ->schema([
                                            ViewField::make('interactions_timeline')
                                                ->label('')
                                                ->view('filament.components.customer-interactions-timeline')
                                                ->columnSpanFull(),
                                        ]),
                                ]),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpan([
                    'xl' => 2,
                    'lg' => 2,
                    'md' => 6,
                    'default' => 3,
                ]),
        ];
    }
}

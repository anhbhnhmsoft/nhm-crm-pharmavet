<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations\Schemas;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CustomerOperationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->label(__('common.table.name'))
                    ->required(),
                TextInput::make('phone')
                    ->label(__('common.table.phone'))
                    ->tel()
                    ->disabled(),
                TextInput::make('email')
                    ->label(__('telesale.form.email'))
                    ->email(),
                DatePicker::make('birthday')
                    ->label(__('telesale.form.birthday'))
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                Select::make('customer_type')
                    ->label(__('telesale.table.customer_type'))
                    ->options(CustomerType::toOptions()),
                Select::make('product_id')
                    ->label(__('common.table.product'))
                    ->relationship('product', 'name')
                    ->searchable(),
                Select::make('source')
                    ->label(__('telesale.table.source'))
                    ->options(IntegrationType::toOptions()),
                Textarea::make('note')
                    ->label(__('common.table.note'))
                    ->columnSpanFull(),

                Grid::make(3)->schema([
                    Select::make('province_id')
                        ->label(__('telesale.form.province'))
                        ->relationship('province', 'name')
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn(Set $set) => $set('district_id', null)),
                    Select::make('district_id')
                        ->label(__('telesale.form.district'))
                        ->relationship('district', 'name', fn($query, Get $get) => $query->where('province_id', $get('province_id')))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn(Set $set) => $set('ward_id', null)),
                    Select::make('ward_id')
                        ->label(__('telesale.form.ward'))
                        ->relationship('ward', 'name', fn($query, Get $get) => $query->where('district_id', $get('district_id')))
                        ->searchable(),
                ]),
                TextInput::make('address')
                    ->label(__('telesale.form.detailed_address'))
                    ->columnSpanFull(),

                Repeater::make('customerStatusLog')
                    ->label(__('telesale.form.call_history'))
                    ->relationship('customerStatusLog')
                    ->schema([
                        Select::make('to_status')
                            ->label(__('telesale.table.interaction_status'))
                            ->options(InteractionStatus::options())
                            ->disabled(),
                        Textarea::make('note')
                            ->label(__('common.table.note'))
                            ->disabled(),
                        TextInput::make('created_at')
                            ->label(__('common.table.created_at'))
                            ->disabled(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}

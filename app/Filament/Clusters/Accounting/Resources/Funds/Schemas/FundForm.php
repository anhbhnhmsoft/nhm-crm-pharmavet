<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FundForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('accounting.fund.sections.basic'))
                    ->schema([
                        TextInput::make('balance')
                            ->label(__('accounting.fund.balance'))
                            ->numeric()
                            ->disabled()
                            ->prefix('VND'),
                        Toggle::make('is_locked')
                            ->label(__('accounting.fund.is_locked'))
                            ->onIcon('heroicon-m-lock-closed')
                            ->offIcon('heroicon-m-lock-open')
                            ->onColor('danger')
                            ->offColor('success'),
                    ])
                    ->columns(2),
            ]);
    }
}

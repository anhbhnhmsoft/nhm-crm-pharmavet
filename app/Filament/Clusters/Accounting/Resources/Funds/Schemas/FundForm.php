<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\Schemas;

use App\Common\Constants\User\UserRole;
use App\Models\Currency;
use App\Models\Fund;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

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
                            ->disabled(function (?Fund $record): bool {
                                $user = Auth::user();

                                if (!$user || !$record) {
                                    return true;
                                }

                                if ($user->role === UserRole::SUPER_ADMIN->value) {
                                    return false;
                                }

                                return !(
                                    $user->role === UserRole::ADMIN->value
                                    && $user->organization_id === $record->organization_id
                                );
                            })
                            ->prefix(fn (?Fund $record): string => $record?->currency ?? 'VND'),
                        Select::make('currency')
                            ->label(__('Currency'))
                            ->options(fn () => Currency::query()->orderBy('code')->pluck('code', 'code')->toArray())
                            ->searchable()
                            ->default('VND')
                            ->required()
                            ->disabled(function (?Fund $record): bool {
                                $user = Auth::user();

                                if (!$user) {
                                    return true;
                                }

                                if ($user->role === UserRole::SUPER_ADMIN->value) {
                                    return false;
                                }

                                return !(
                                    $record
                                    && $record->organization?->is_foreign
                                    && $user->role === UserRole::ADMIN->value
                                    && $user->organization_id === $record->organization_id
                                );
                            }),
                        Toggle::make('is_locked')
                            ->label(__('accounting.fund.is_locked'))
                            ->onIcon('heroicon-m-lock-closed')
                            ->offIcon('heroicon-m-lock-open')
                            ->onColor('danger')
                            ->offColor('success'),
                    ])
                    ->columnSpanFull(),
                    
            ]);
    }
}

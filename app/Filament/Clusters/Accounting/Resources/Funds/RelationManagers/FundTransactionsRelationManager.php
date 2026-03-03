<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\RelationManagers;

use App\Common\Constants\Organization\FundTransactionStatus;
use App\Common\Constants\Organization\FundTransactionType;
use App\Models\FundTransaction;
use App\Services\FundService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FundTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $recordTitleAttribute = 'transaction_code';

    public static function getTitle($ownerRecord, $pageClass): string
    {
        return __('accounting.fund.sections.transactions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('accounting.fund_transaction.type'))
                    ->options([
                        FundTransactionType::DEPOSIT->value => __('accounting.fund_transaction.types.in'),
                        FundTransactionType::WITHDRAW->value => __('accounting.fund_transaction.types.out'),
                    ])
                    ->required(),
                TextInput::make('amount')
                    ->label(__('accounting.fund_transaction.amount'))
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('description')
                    ->label(__('accounting.fund_transaction.description'))
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('accounting.fund_transaction.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('accounting.fund_transaction.type'))
                    ->formatStateUsing(fn($state) => match ($state) {
                        FundTransactionType::DEPOSIT->value => __('accounting.fund_transaction.types.in'),
                        FundTransactionType::WITHDRAW->value => __('accounting.fund_transaction.types.out'),
                        default => 'Unknown'
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        FundTransactionType::DEPOSIT->value => 'success',
                        FundTransactionType::WITHDRAW->value => 'danger',
                        default => 'gray'
                    }),
                TextColumn::make('amount')
                    ->label(__('accounting.fund_transaction.amount'))
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('balance_after')
                    ->label(__('accounting.fund_transaction.balance_after'))
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('accounting.fund_transaction.description'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('accounting.fund_transaction.status'))
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        FundTransactionStatus::PENDING->value => __('accounting.fund_transaction.statuses.pending'),
                        FundTransactionStatus::COMPLETED->value => __('accounting.fund_transaction.statuses.completed'),
                        FundTransactionStatus::CANCELLED->value => __('accounting.fund_transaction.statuses.cancelled'),
                        default => 'Unknown'
                    })
                    ->color(fn($state) => match ($state) {
                        FundTransactionStatus::PENDING->value => 'warning',
                        FundTransactionStatus::COMPLETED->value => 'success',
                        FundTransactionStatus::CANCELLED->value => 'danger',
                        default => 'gray'
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn() => !$this->getOwnerRecord()->is_locked)
                    ->action(function (array $data) {
                        $fund = $this->getOwnerRecord();
                        $service = app(FundService::class);

                        $result = $service->createTransaction($fund, $data);

                        if ($result->isError()) {
                            Notification::make()
                                ->title($result->getMessage())
                                ->danger()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->title(__('common.notification.success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\RelationManagers;

use App\Common\Constants\Organization\FundLockAction;
use App\Common\Constants\Organization\FundLockScope;
use App\Common\Constants\Organization\FundTransactionStatus;
use App\Common\Constants\Organization\FundTransactionType;
use App\Models\Team;
use App\Models\User;
use App\Models\FundTransaction;
use App\Services\FundService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

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
                DatePicker::make('transaction_date')
                    ->label(__('accounting.fund_transaction.transaction_date'))
                    ->default(now()->toDateString())
                    ->required()
                    ->extraInputAttributes(['required' => false])
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->validationMessages([
                        'required' => __('common.error.required'),
                    ]),
                Select::make('type')
                    ->label(__('accounting.fund_transaction.type'))
                    ->options([
                        FundTransactionType::DEPOSIT->value => __('accounting.fund_transaction.types.in'),
                        FundTransactionType::WITHDRAW->value => __('accounting.fund_transaction.types.out'),
                    ])
                    ->required()
                    ->extraInputAttributes(['required' => false])
                    ->validationMessages([
                        'required' => __('common.error.required'),
                    ]),
                TextInput::make('counterparty_name')
                    ->label(__('accounting.fund_transaction.counterparty_name'))
                    ->required()
                    ->extraInputAttributes(['required' => false])
                    ->validationMessages([
                        'required' => __('common.error.required'),
                    ]),
                TextInput::make('amount')
                    ->label(__('accounting.fund_transaction.amount'))
                    ->numeric()
                    ->required()
                    ->extraInputAttributes([
                        'type' => 'text',
                        'inputmode' => 'decimal',
                        'required' => false,
                        'min' => null,
                        'max' => null,
                        'step' => null,
                    ])
                    ->minValue(0.01)
                    ->maxValue(999999999999999.99)
                    ->validationAttribute(__('accounting.fund_transaction.amount'))
                    ->validationMessages([
                        'required' => __('common.error.required'),
                        'numeric' => __('common.error.numeric'),
                        'min' => __('common.error.min_value', ['min' => 0.01]),
                        'max' => __('common.error.max_value', ['max' => 999999999999999.99]),
                    ]),
                Select::make('currency')
                    ->label(__('accounting.fund_transaction.currency'))
                    ->options(fn () => \App\Models\Currency::query()->orderBy('code')->pluck('code', 'code')->toArray())
                    ->default('VND')
                    ->searchable()
                    ->required()
                    ->extraInputAttributes(['required' => false])
                    ->validationMessages([
                        'required' => __('common.error.required'),
                    ]),
                TextInput::make('exchange_rate')
                    ->label(__('accounting.fund_transaction.exchange_rate'))
                    ->numeric()
                    ->extraInputAttributes([
                        'type' => 'text',
                        'inputmode' => 'decimal',
                        'required' => false,
                        'min' => null,
                        'max' => null,
                        'step' => null,
                    ])
                    ->validationMessages([
                        'numeric' => __('common.error.numeric'),
                    ])
                    ->visible(fn ($get) => (string) $get('currency') !== 'VND'),
                TextInput::make('purpose')
                    ->label(__('accounting.fund_transaction.purpose'))
                    ->required()
                    ->extraInputAttributes(['required' => false])
                    ->validationMessages([
                        'required' => __('common.error.required'),
                    ]),
                TextInput::make('description')
                    ->label(__('accounting.fund_transaction.description'))
                    ->required()
                    ->extraInputAttributes(['required' => false])
                    ->validationMessages([
                        'required' => __('common.error.required'),
                    ]),
                Textarea::make('note')
                    ->label(__('accounting.fund_transaction.note'))
                    ->rows(2),
                FileUpload::make('attachments')
                    ->label(__('accounting.fund_transaction.attachments'))
                    ->multiple()
                    ->disk('public')
                    ->directory('fund-transactions')
                    ->visibility('public')
                    ->image()
                    ->imageEditor()
                    ->previewable(true)
                    ->formatStateUsing(function (?FundTransaction $record): array {
                        return $record ? $record->attachments->pluck('file_path')->toArray() : [];
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')
                    ->label(__('accounting.fund_transaction.transaction_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('transaction_code')
                    ->label(__('accounting.fund_transaction.transaction_code'))
                    ->searchable(),
                TextColumn::make('counterparty_name')
                    ->label(__('accounting.fund_transaction.counterparty_name'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('accounting.fund_transaction.type'))
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        FundTransactionType::DEPOSIT->value => __('accounting.fund_transaction.types.in'),
                        FundTransactionType::WITHDRAW->value => __('accounting.fund_transaction.types.out'),
                        default => '-',
                    })
                    ->badge()
                    ->color(fn ($state) => (int) $state === FundTransactionType::DEPOSIT->value ? 'success' : 'danger'),
                TextColumn::make('amount')
                    ->label(__('accounting.fund_transaction.amount'))
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2) . ' ' . ($record->currency ?? 'VND'))
                    ->sortable(),
                TextColumn::make('balance_after')
                    ->label(__('accounting.fund_transaction.balance_after'))
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2) . ' ' . ($record->fund?->currency ?? 'VND'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('accounting.fund_transaction.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        FundTransactionStatus::PENDING->value => __('accounting.fund_transaction.statuses.pending'),
                        FundTransactionStatus::COMPLETED->value => __('accounting.fund_transaction.statuses.completed'),
                        FundTransactionStatus::CANCELLED->value => __('accounting.fund_transaction.statuses.cancelled'),
                        default => '-',
                    }),
                TextColumn::make('attachments_count')
                    ->label(__('accounting.fund_transaction.attachment_versions'))
                    ->counts('attachments'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('accounting.fund_transaction.actions.create'))
                    ->modalHeading(__('accounting.fund_transaction.actions.create'))
                    ->visible(fn () => $this->canPerformFundAction(FundLockAction::ADD))
                    ->action(function (CreateAction $action, array $data, FundService $service) {
                        $result = $service->createTransaction($this->getOwnerRecord(), $data, Auth::user());

                        if ($result->isError()) {
                            Notification::make()->title($result->getMessage())->danger()->send();
                            $action->halt();
                        }
                    })
                    ->after(fn () => redirect(request()->header('Referer'))),
                Action::make('configureLockRule')
                    ->label(__('accounting.fund_lock.configure'))
                    ->icon('heroicon-o-lock-closed')
                    ->modalHeading(__('accounting.fund_lock.configure'))
                    ->modalSubmitActionLabel(__('common.action.save'))
                    ->modalCancelActionLabel(__('common.action.cancel'))
                    ->form([
                        Select::make('actions')
                            ->label(__('accounting.fund_lock.action'))
                            ->options(FundLockAction::options())
                            ->multiple()
                            ->minItems(1)
                            ->native(false)
                            ->placeholder(__('accounting.fund_lock.placeholders.actions'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_items', ['min' => 1]),
                            ]),
                        Select::make('scope_type')
                            ->label(__('accounting.fund_lock.scope'))
                            ->options(FundLockScope::options())
                            ->native(false)
                            ->placeholder(__('accounting.fund_lock.placeholders.scope'))
                            ->live()
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),
                        Select::make('user_ids')
                            ->label(__('accounting.fund_lock.users'))
                            ->multiple()
                            ->minItems(1)
                            ->native(false)
                            ->placeholder(__('accounting.fund_lock.placeholders.users'))
                            ->options(fn () => User::query()
                                ->where('organization_id', $this->getOwnerRecord()->organization_id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->required(fn ($get) => $get('scope_type') === FundLockScope::USER->value)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_items', ['min' => 1]),
                            ])
                            ->visible(fn ($get) => $get('scope_type') === FundLockScope::USER->value),
                        Select::make('team_ids')
                            ->label(__('accounting.fund_lock.teams'))
                            ->multiple()
                            ->minItems(1)
                            ->native(false)
                            ->placeholder(__('accounting.fund_lock.placeholders.teams'))
                            ->options(fn () => Team::query()
                                ->where('organization_id', $this->getOwnerRecord()->organization_id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->required(fn ($get) => $get('scope_type') === FundLockScope::TEAM->value)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min' => __('common.error.min_items', ['min' => 1]),
                            ])
                            ->visible(fn ($get) => $get('scope_type') === FundLockScope::TEAM->value),
                        Select::make('is_locked')
                            ->label(__('accounting.fund_lock.status'))
                            ->options([
                                1 => __('accounting.fund_lock.locked'),
                                0 => __('accounting.fund_lock.unlocked'),
                            ])
                            ->native(false)
                            ->placeholder(__('accounting.fund_lock.placeholders.status'))
                            ->default(1)
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),
                    ])
                    ->action(function (Action $action, array $data, FundService $service) {
                        $result = $service->upsertLockRule($this->getOwnerRecord(), $data, Auth::user());
                        if ($result->isError()) {
                            Notification::make()->title($result->getMessage())->danger()->send();
                            $action->halt();
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn () => !$this->canPerformFundAction(FundLockAction::EDIT))
                    ->action(function (EditAction $action, array $data, FundTransaction $record, FundService $service) {
                        if (!$service->canPerformAction($this->getOwnerRecord(), Auth::user(), FundLockAction::EDIT)) {
                            Notification::make()->title($service->getDeniedMessage(FundLockAction::EDIT))->danger()->send();
                            $action->halt();
                        }

                        $result = $service->updateTransaction($record, $data, Auth::user());

                        if ($result->isError()) {
                            Notification::make()->title($result->getMessage())->danger()->send();
                            $action->halt();
                        }
                    })
                    ->after(fn () => redirect(request()->header('Referer'))),
                DeleteAction::make()
                    ->hidden(fn () => !$this->canPerformFundAction(FundLockAction::DELETE))
                    ->action(function (DeleteAction $action, FundTransaction $record, FundService $service) {
                        if (!$service->canPerformAction($this->getOwnerRecord(), Auth::user(), FundLockAction::DELETE)) {
                            Notification::make()->title($service->getDeniedMessage(FundLockAction::DELETE))->danger()->send();
                            $action->halt();
                        }
                        $result = $service->deleteTransaction($record, Auth::user());
                        if ($result->isError()) {
                            Notification::make()->title($result->getMessage())->danger()->send();
                            $action->halt();
                        }
                    })
                    ->after(fn () => redirect(request()->header('Referer'))),
                Action::make('attachmentHistory')
                    ->label(__('accounting.fund_transaction.attachment_history'))
                    ->icon('heroicon-o-paper-clip')
                    ->modalHeading(__('accounting.fund_transaction.attachment_history'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('common.action.cancel'))
                    ->modalContent(function ($record) {
                        $attachments = $record->attachments()->with('uploader')->orderByDesc('version')->get();
                        return view('filament.clusters.accounting.funds.attachment-history', [
                            'attachments' => $attachments,
                        ]);
                    }),
            ])
            ->emptyStateHeading(__('accounting.fund_transaction.notifications.empty_state_title'))
            ->emptyStateDescription(__('accounting.fund_transaction.notifications.empty_state_description'))
            ->defaultSort('transaction_date', 'desc');
    }

    protected function canPerformFundAction(FundLockAction $action): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        return app(FundService::class)->canPerformAction($this->getOwnerRecord(), $user, $action);
    }
}

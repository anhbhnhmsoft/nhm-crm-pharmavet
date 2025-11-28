<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations\Tables;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\ReasonInteraction;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerOperationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('telesale.table.data_code'))
                    ->searchable()
                    ->sortable()
                    ->size('sm')
                    ->weight('bold'),

                TextColumn::make('username')
                    ->label(__('telesale.table.customer_name'))
                    ->searchable(['username', 'phone'])
                    ->size('sm')
                    ->weight('medium'),

                TextColumn::make('source')
                    ->label(__('telesale.table.source'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(int $state) => IntegrationType::getLabel($state))
                    ->size('sm'),

                TextColumn::make('customer_type')
                    ->label(__('telesale.table.customer_type'))
                    ->badge()
                    ->color(fn(int $state): string => match ($state) {
                        CustomerType::NEW->value => 'success',
                        CustomerType::NEW_DUPLICATE->value => 'warning',
                        CustomerType::OLD_CUSTOMER->value => 'danger',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn($state) => CustomerType::getLabel($state))
                    ->size('sm'),

                TextColumn::make('next_action_at')
                    ->label(__('telesale.table.next_action'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder(__('telesale.messages.no_schedule'))
                    ->color(fn($state) => $state && $state->isPast() ? 'danger' : 'success')
                    ->size('sm'),

                TextColumn::make('created_at')
                    ->label(__('telesale.table.date_received'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable()
                    ->size('sm'),
                TextColumn::make('interaction_status')
                    ->label(__('telesale.table.interaction_status'))
                    ->badge()
                    ->formatStateUsing(fn($state) => InteractionStatus::getLabelStatus($state))
                    ->size('sm'),
                TextColumn::make('blackList')
                    ->label(__('telesale.table.blacklist'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn($state) => $state ? __('telesale.table.blacklist') : __('telesale.table.unblacklist'))
                    ->size('sm'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label(__('common.action.view'))
                        ->tooltip(__('common.tooltip.view'))
                        ->icon('heroicon-o-eye'),

                    // FIRST_CALL Action
                    Action::make('first_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::FIRST_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('danger')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                TextInput::make('address')
                                    ->label(__('common.table.address'))
                                    ->disabled()
                                    ->default($record->address)
                                    ->visible($record->address ? true : false),
                                TextInput::make('ward')
                                    ->label(__('common.table.ward'))
                                    ->disabled()
                                    ->default($record->ward?->name)
                                    ->visible($record->ward ? true : false),
                                TextInput::make('district')
                                    ->label(__('common.table.district'))
                                    ->disabled()
                                    ->default($record->district?->name)
                                    ->visible($record->district ? true : false),
                                TextInput::make('province')
                                    ->label(__('common.table.province'))
                                    ->disabled()
                                    ->default($record->province?->name)
                                    ->visible($record->province ? true : false),
                                TextInput::make('email')->disabled()->default($record->email)->visible($record->email ? true : false),
                                TextInput::make('note')
                                    ->label(__('common.table.note'))
                                    ->disabled()
                                    ->default($record->note)
                                    ->visible($record->note ? true : false),
                                TextInput::make('source')
                                    ->disabled()
                                    ->label(__('common.table.source'))
                                    ->default(IntegrationType::getLabel($record->source))
                                    ->visible($record->source ? true : false),
                                TextInput::make('product')
                                    ->disabled()
                                    ->label(__('common.table.product'))
                                    ->default($record->product?->name)
                                    ->visible($record->product ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(
                                        fn($get) =>
                                        ReasonInteraction::requiresScheduling((int)$get('reason'))
                                    )
                                    ->visible(
                                        fn($get) =>
                                        ReasonInteraction::requiresScheduling((int)$get('reason'))
                                    )
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                // Xác định trạng thái tiếp theo dựa trên lý do
                                $nextStatus = ReasonInteraction::getNextStatus(
                                    $data['reason'],
                                    $record->interaction_status
                                );

                                // Tạo log
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                // Cập nhật trạng thái
                                $record->interaction_status = $nextStatus;

                                // Nếu cần lên lịch (CALL_BACK, THINK_MORE)
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }

                                $record->save();

                                Notification::make()
                                    ->title(__('common.success.update_success'))
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()
                                    ->title(__('common.error.update_error'))
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::FIRST_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::FIRST_CALL->value),

                    // SECOND_CALL Action
                    Action::make('second_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::SECOND_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('warning')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.validation.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::SECOND_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::SECOND_CALL->value),

                    // THIRD_CALL Action
                    Action::make('third_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::THIRD_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('info')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.validation.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::THIRD_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::THIRD_CALL->value),

                    // FOURTH_CALL Action
                    Action::make('fourth_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::FOURTH_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('primary')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.validation.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::FOURTH_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::FOURTH_CALL->value),

                    // FIFTH_CALL Action
                    Action::make('fifth_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::FIFTH_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('success')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.validation.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::FIFTH_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::FIFTH_CALL->value),

                    // SIXTH_CALL Action
                    Action::make('sixth_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::SIXTH_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('gray')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.validation.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::SIXTH_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::SIXTH_CALL->value),

                    // USER_MANUAL Action
                    Action::make('user_manual')
                        ->label(InteractionStatus::getLabel(InteractionStatus::USER_MANUAL->value))
                        ->icon('heroicon-o-book-open')
                        ->color('indigo')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.validation.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::USER_MANUAL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::USER_MANUAL->value),

                    // SECOND_CARE Action
                    Action::make('second_care')
                        ->label(InteractionStatus::getLabel(InteractionStatus::SECOND_CARE->value))
                        ->icon('heroicon-o-heart')
                        ->color('pink')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.validation.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::SECOND_CARE->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::SECOND_CARE->value),

                    // THIRD_CARE Action
                    Action::make('third_care')
                        ->label(InteractionStatus::getLabel(InteractionStatus::THIRD_CARE->value))
                        ->icon('heroicon-o-heart')
                        ->color('rose')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.validation.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int)$get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.validation.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::THIRD_CARE->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::THIRD_CARE->value),

                    DeleteAction::make()
                        ->label(__('common.action.delete'))
                        ->tooltip(__('common.tooltip.delete'))
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.delete_title'))
                        ->modalDescription(__('common.modal.delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete'))
                        ->visible(fn($record) => ! $record->trashed()),

                    RestoreAction::make()
                        ->label(__('common.action.restore'))
                        ->tooltip(__('common.tooltip.restore'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn($record) => $record->trashed()),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('common.action.delete'))
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.delete_title'))
                        ->modalDescription(__('common.modal.delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete')),

                    RestoreBulkAction::make()
                        ->label(__('common.action.restore'))
                        ->visible(fn($livewire) => $livewire->tableFilters['trashed']['value'] ?? null === 'only'),

                    ForceDeleteBulkAction::make()
                        ->label(__('common.action.force_delete'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.force_delete_title'))
                        ->modalDescription(__('common.modal.force_delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->persistFiltersInSession()
            ->poll('30s');
    }
}

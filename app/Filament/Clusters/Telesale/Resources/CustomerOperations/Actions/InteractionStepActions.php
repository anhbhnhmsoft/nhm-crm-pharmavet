<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations\Actions;

use App\Common\Constants\Customer\ReasonInteraction;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Services\Telesale\TelesaleInteractionCommand;
use App\Services\Telesale\TelesaleInteractionWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class InteractionStepActions
{
    protected static function refreshInteractionUi(object $livewire, mixed $record = null): void
    {
        if ($record && method_exists($record, 'refresh')) {
            $record->refresh();
        }

        if (method_exists($livewire, 'resetTable')) {
            $livewire->resetTable();

            return;
        }

        if (method_exists($livewire, 'dispatch')) {
            $livewire->dispatch('$refresh');
        }
    }

    public static function make(): array
    {
        return collect(self::configs())
            ->map(fn(array $config) => self::makeAction($config))
            ->all();
    }

    public static function reasonField(string $name = 'reason'): Select
    {
        return Select::make($name)
            ->options(self::interactionReasonOptions())
            ->label(__('common.table.result'))
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, Set $set, ?Get $get = null) use ($name): void {
                $nextActionField = $name === 'interaction_reason' ? 'interaction_next_action_at' : 'next_action_at';

                if (! ReasonInteraction::requiresScheduling((int) $state)) {
                    $set($nextActionField, null);
                }
            })
            ->extraInputAttributes(['required' => false])
            ->validationMessages([
                'required' => __('common.error.required'),
            ]);
    }

    protected static function interactionReasonOptions(): array
    {
        return collect(ReasonInteraction::options())
            ->except(ReasonInteraction::CLOSING_ORDER->value)
            ->all();
    }

    public static function nextActionField(string $reasonField = 'reason', string $name = 'next_action_at'): DateTimePicker
    {
        return DateTimePicker::make($name)
            ->label(__('telesale.table.next_action'))
            ->native(false)
            ->displayFormat('d/m/Y H:i')
            ->seconds(false)
            ->minutesStep(15)
            ->required(fn(Get $get) => ReasonInteraction::requiresScheduling((int) $get($reasonField)))
            ->visible(fn(Get $get) => ReasonInteraction::requiresScheduling((int) $get($reasonField)))
            ->helperText(__('telesale.helper.schedule_callback'))
            ->validationMessages([
                'required' => __('common.error.required'),
            ]);
    }

    public static function noteField(string $name = 'note'): Textarea
    {
        return Textarea::make($name)
            ->label(__('telesale.form.content'))
            ->placeholder(__('telesale.form.content_placeholder'))
            ->rows(3);
    }

    protected static function makeAction(array $config): Action
    {
        $status = $config['status'];

        return Action::make($config['name'])
            ->label(InteractionStatus::getLabel($status))
            ->icon($config['icon'])
            ->color($config['color'])
            ->schema(fn($record) => [
                Grid::make(2)->schema([
                    ...self::customerPreviewFields($record, $config['detailed'] ?? false),
                    self::reasonField(),
                    self::nextActionField(),
                    self::noteField(),
                ]),
            ])
            ->action(fn(array $data, $record, $livewire) => self::handleAction($record, $data, $livewire))
            ->modalHeading(InteractionStatus::getLabel($status))
            ->modalDescription(fn($record) => self::modalDescription($record))
            ->modalSubmitActionLabel(__('common.action.save'))
            ->visible(fn($record): bool => (int) $record->interaction_status === $status);
    }

    protected static function handleAction($record, array $data, ?object $livewire = null): void
    {
        try {
            app(TelesaleInteractionWorkflowService::class)->execute(
                TelesaleInteractionCommand::fromArray($record, $data, (int) Auth::id(), 'table_action')
            );

            if ($livewire) {
                self::refreshInteractionUi($livewire, $record);
            }

            Notification::make()
                ->title(__('common.success.update_success'))
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Telesale interaction action error', [
                'customer_id' => $record->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            Notification::make()
                ->title(__('common.error.update_error'))
                ->warning()
                ->send();
        }
    }

    protected static function customerPreviewFields($record, bool $detailed = false): array
    {
        $fields = [
            TextInput::make('phone_preview')
                ->label(__('common.table.phone'))
                ->disabled()
                ->default($record->phone)
                ->visible(filled($record->phone)),
            TextInput::make('name_preview')
                ->label(__('common.table.name'))
                ->disabled()
                ->default($record->username)
                ->visible(filled($record->username)),
        ];

        if (! $detailed) {
            return $fields;
        }

        return [
            ...$fields,
            TextInput::make('address_preview')
                ->label(__('common.table.address'))
                ->disabled()
                ->default($record->address)
                ->visible(filled($record->address)),
            TextInput::make('ward_preview')
                ->label(__('common.table.ward'))
                ->disabled()
                ->default($record->ward?->name)
                ->visible(filled($record->ward?->name)),
            TextInput::make('district_preview')
                ->label(__('common.table.district'))
                ->disabled()
                ->default($record->district?->name)
                ->visible(filled($record->district?->name)),
            TextInput::make('province_preview')
                ->label(__('common.table.province'))
                ->disabled()
                ->default($record->province?->name)
                ->visible(filled($record->province?->name)),
            TextInput::make('email_preview')
                ->label(__('telesale.form.email'))
                ->disabled()
                ->default($record->email)
                ->visible(filled($record->email)),
            Textarea::make('customer_note_preview')
                ->label(__('common.table.note'))
                ->disabled()
                ->default($record->note)
                ->rows(2)
                ->visible(filled($record->note)),
            TextInput::make('source_preview')
                ->disabled()
                ->label(__('common.table.source'))
                ->default(IntegrationType::getLabel((int) $record->source))
                ->visible(filled($record->source)),
            TextInput::make('product_preview')
                ->disabled()
                ->label(__('common.table.product'))
                ->default($record->product?->name)
                ->visible(filled($record->product?->name)),
        ];
    }

    protected static function modalDescription($record): string
    {
        $parts = [
            __('telesale.table.data_code') . ': ' . $record->id,
        ];

        if (filled($record->username)) {
            $parts[] = __('telesale.table.customer_name') . ': ' . $record->username;
        }

        if (filled($record->phone)) {
            $parts[] = __('common.table.phone') . ': ' . $record->phone;
        }

        return implode(' | ', $parts);
    }

    protected static function configs(): array
    {
        return [
            [
                'name' => 'first_call',
                'status' => InteractionStatus::FIRST_CALL->value,
                'icon' => 'heroicon-o-phone',
                'color' => 'danger',
                'detailed' => true,
            ],
            [
                'name' => 'second_call',
                'status' => InteractionStatus::SECOND_CALL->value,
                'icon' => 'heroicon-o-phone',
                'color' => 'warning',
            ],
            [
                'name' => 'third_call',
                'status' => InteractionStatus::THIRD_CALL->value,
                'icon' => 'heroicon-o-phone',
                'color' => 'info',
            ],
            [
                'name' => 'fourth_call',
                'status' => InteractionStatus::FOURTH_CALL->value,
                'icon' => 'heroicon-o-phone',
                'color' => 'primary',
            ],
            [
                'name' => 'fifth_call',
                'status' => InteractionStatus::FIFTH_CALL->value,
                'icon' => 'heroicon-o-phone',
                'color' => 'success',
            ],
            [
                'name' => 'sixth_call',
                'status' => InteractionStatus::SIXTH_CALL->value,
                'icon' => 'heroicon-o-phone',
                'color' => 'gray',
            ],
            [
                'name' => 'user_manual',
                'status' => InteractionStatus::USER_MANUAL->value,
                'icon' => 'heroicon-o-book-open',
                'color' => 'indigo',
            ],
            [
                'name' => 'second_care',
                'status' => InteractionStatus::SECOND_CARE->value,
                'icon' => 'heroicon-o-heart',
                'color' => 'pink',
            ],
            [
                'name' => 'third_care',
                'status' => InteractionStatus::THIRD_CARE->value,
                'icon' => 'heroicon-o-heart',
                'color' => 'rose',
            ],
        ];
    }
}

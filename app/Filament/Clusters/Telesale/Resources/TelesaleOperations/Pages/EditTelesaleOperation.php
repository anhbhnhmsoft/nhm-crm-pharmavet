<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages;

use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\TelesaleOperationResource;
use App\Models\Order;
use App\Services\Telesale\TelesaleInteractionCommand;
use App\Services\Telesale\TelesaleInteractionWorkflowService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditTelesaleOperation extends EditRecord
{
    protected static string $resource = TelesaleOperationResource::class;

    protected TelesaleInteractionWorkflowService $interactionWorkflowService;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function boot(TelesaleInteractionWorkflowService $interactionWorkflowService): void
    {
        $this->interactionWorkflowService = $interactionWorkflowService;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $order = Order::query()
            ->with('items')
            ->where('customer_id', $this->record->getKey())
            ->latest()
            ->first();

        if (! $order) {
            return $data;
        }

        $data['warehouse_id'] = $order->warehouse_id;
        $data['order_items'] = $order->items
            ->map(fn($item): array => [
                'product_id' => $item->product_id,
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price,
            ])
            ->values()
            ->all();
        $data['cod_fee'] = $order->cod_fee;
        $data['deposit'] = $order->deposit;
        $data['discount'] = $order->discount;
        $data['ck1'] = $order->ck1;
        $data['ck2'] = $order->ck2;
        $data['total_amount'] = $order->total_amount;

        return $data;
    }

    protected function afterSave(): void
    {
        $data = $this->form->getRawState();

        if (! filled($data['interaction_reason'] ?? null)) {
            return;
        }

        try {
            $result = $this->interactionWorkflowService->execute(
                TelesaleInteractionCommand::fromArray($this->record, $data, (int) Auth::id(), 'edit_form')
            );

            $this->record->refresh();

            $this->fillFormWithDataAndCallHooks($this->record, [
                'interaction_reason' => null,
                'interaction_note' => null,
                'interaction_next_action_at' => null,
            ]);

            Notification::make()
                ->title($result->message)
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Edit telesale interaction workflow error', [
                'customer_id' => $this->record->id,
                'message' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title(__('common.error.update_error'))
                ->danger()
                ->send();
        }
    }
}

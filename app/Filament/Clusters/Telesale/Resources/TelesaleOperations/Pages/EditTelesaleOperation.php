<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages;

use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\TelesaleOperationResource;
use App\Models\Order;
use App\Services\CustomerService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTelesaleOperation extends EditRecord
{
    protected static string $resource = TelesaleOperationResource::class;

    protected CustomerService $customerService;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function boot(CustomerService $customerService): void
    {
        $this->customerService = $customerService;
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

        if (! filled($data['new_interaction_status'] ?? null)) {
            return;
        }

        $result = $this->customerService->saveInteraction($this->record, $data);

        if (! $result->isSuccess()) {
            return;
        }

        $this->record->refresh();

        $this->form->fill([
            'new_interaction_status' => null,
            'new_interaction_content' => null,
            'next_action_at' => $this->record->next_action_at,
        ]);
    }
}

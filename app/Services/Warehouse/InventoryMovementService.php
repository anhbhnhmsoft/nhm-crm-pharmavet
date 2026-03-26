<?php

namespace App\Services\Warehouse;

use App\Common\Constants\Warehouse\InventoryMovementType;
use App\Common\Constants\Warehouse\StatusTicket;
use App\Common\Constants\Warehouse\TypeTicket;
use App\Core\ServiceReturn;
use App\Models\InventoryTicket;
use App\Models\Order;
use App\Models\ProductWarehouse;
use App\Repositories\InventoryMovementRepository;
use App\Repositories\ProductWarehouseRepository;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    public function __construct(
        protected ProductWarehouseRepository $productWarehouseRepository,
        protected InventoryMovementRepository $inventoryMovementRepository,
    ) {
    }

    public function approveTicket(InventoryTicket $ticket, int $actorId, ?string $reasonCode = null, ?string $reasonNote = null): ServiceReturn
    {
        if ((int) $ticket->status !== StatusTicket::DRAFT->value) {
            return ServiceReturn::error(__('warehouse.ticket.errors.only_draft_can_be_approved'));
        }

        try {
            DB::transaction(function () use ($ticket, $actorId, $reasonCode, $reasonNote) {
                $oldStatus = (int) $ticket->status;

                $this->applyTicketCompletion($ticket, $actorId, $reasonCode, $reasonNote);

                $ticket->update([
                    'status' => StatusTicket::COMPLETED->value,
                    'approved_by' => $actorId,
                    'approved_at' => now(),
                    'updated_by' => $actorId,
                ]);

                $this->logTicketAction(
                    ticket: $ticket,
                    action: 'approve',
                    oldStatus: $oldStatus,
                    newStatus: StatusTicket::COMPLETED->value,
                    actorId: $actorId,
                    reasonCode: $reasonCode,
                    reasonNote: $reasonNote,
                    metadata: [
                        'type' => $ticket->type,
                        'details_count' => $ticket->details()->count(),
                    ]
                );
            });

            return ServiceReturn::success(message: __('warehouse.ticket.action.approved'));
        } catch (\Throwable $exception) {
            return ServiceReturn::error($exception->getMessage());
        }
    }

    public function cancelTicket(InventoryTicket $ticket, int $actorId, string $reasonCode, string $reasonNote): ServiceReturn
    {
        if ($reasonCode === '' || $reasonNote === '') {
            return ServiceReturn::error(__('warehouse.ticket.errors.reason_required'));
        }

        try {
            DB::transaction(function () use ($ticket, $actorId, $reasonCode, $reasonNote) {
                $oldStatus = (int) $ticket->status;

                $ticket->update([
                    'status' => StatusTicket::CANCELLED->value,
                    'updated_by' => $actorId,
                ]);

                $this->logTicketAction(
                    ticket: $ticket,
                    action: 'cancel',
                    oldStatus: $oldStatus,
                    newStatus: StatusTicket::CANCELLED->value,
                    actorId: $actorId,
                    reasonCode: $reasonCode,
                    reasonNote: $reasonNote,
                    metadata: ['type' => $ticket->type]
                );
            });

            return ServiceReturn::success(message: __('warehouse.ticket.action.cancelled'));
        } catch (\Throwable $exception) {
            return ServiceReturn::error($exception->getMessage());
        }
    }

    public function applyTicketCompletion(InventoryTicket $ticket, int $actorId, ?string $reasonCode = null, ?string $reasonNote = null): void
    {
        $ticket->loadMissing('details');

        foreach ($ticket->details as $detail) {
            $qty = (int) $detail->quantity;
            if ($qty <= 0) {
                continue;
            }

            $type = (int) $ticket->type;
            if ($type === TypeTicket::IMPORT->value || $type === TypeTicket::CANCEL_EXPORT->value) {
                $this->increaseStock(
                    organizationId: (int) $ticket->organization_id,
                    warehouseId: (int) $ticket->warehouse_id,
                    productId: (int) $detail->product_id,
                    quantity: $qty,
                    actorId: $actorId,
                    movementType: InventoryMovementType::IN,
                    refType: 'inventory_ticket',
                    refId: (int) $ticket->id,
                    reasonCode: $reasonCode,
                    reasonNote: $reasonNote,
                );
                $this->logDetailMovement($ticket, (int) $detail->product_id, 'ticket_in', $actorId, $reasonCode, $reasonNote, $qty);
                continue;
            }

            if ($type === TypeTicket::EXPORT->value) {
                $this->decreaseStock(
                    organizationId: (int) $ticket->organization_id,
                    warehouseId: (int) $ticket->warehouse_id,
                    productId: (int) $detail->product_id,
                    quantity: $qty,
                    actorId: $actorId,
                    movementType: InventoryMovementType::OUT,
                    refType: 'inventory_ticket',
                    refId: (int) $ticket->id,
                    reasonCode: $reasonCode,
                    reasonNote: $reasonNote,
                );
                $this->logDetailMovement($ticket, (int) $detail->product_id, 'ticket_out', $actorId, $reasonCode, $reasonNote, $qty);
                continue;
            }

            if ($type === TypeTicket::TRANSFER->value) {
                $sourceId = (int) $ticket->source_warehouse_id;
                $targetId = (int) $ticket->target_warehouse_id;
                if ($sourceId <= 0 || $targetId <= 0) {
                    throw new \RuntimeException(__('warehouse.ticket.errors.transfer_warehouse_required'));
                }

                $this->decreaseStock(
                    organizationId: (int) $ticket->organization_id,
                    warehouseId: $sourceId,
                    productId: (int) $detail->product_id,
                    quantity: $qty,
                    actorId: $actorId,
                    movementType: InventoryMovementType::TRANSFER_OUT,
                    refType: 'inventory_ticket',
                    refId: (int) $ticket->id,
                    reasonCode: $reasonCode,
                    reasonNote: $reasonNote,
                );

                $this->increaseStock(
                    organizationId: (int) $ticket->organization_id,
                    warehouseId: $targetId,
                    productId: (int) $detail->product_id,
                    quantity: $qty,
                    actorId: $actorId,
                    movementType: InventoryMovementType::TRANSFER_IN,
                    refType: 'inventory_ticket',
                    refId: (int) $ticket->id,
                    reasonCode: $reasonCode,
                    reasonNote: $reasonNote,
                );
                $this->logDetailMovement($ticket, (int) $detail->product_id, 'ticket_transfer', $actorId, $reasonCode, $reasonNote, $qty);
            }
        }
    }

    public function reserveForOrder(Order $order, int $actorId): void
    {
        if ((int) $order->warehouse_id <= 0) {
            throw new \RuntimeException(__('telesale.messages.warehouse_required'));
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $this->changePending(
                organizationId: (int) $order->organization_id,
                warehouseId: (int) $order->warehouse_id,
                productId: (int) $item->product_id,
                pendingChange: (int) $item->quantity,
                actorId: $actorId,
                movementType: InventoryMovementType::RESERVE,
                refType: 'order',
                refId: (int) $order->id,
                reasonCode: 'order_confirmed',
                reasonNote: null,
                validateAvailable: true,
            );
        }
    }

    public function releaseReservation(Order $order, int $actorId, string $reasonCode, ?string $reasonNote = null): void
    {
        if ((int) $order->warehouse_id <= 0) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $this->changePending(
                organizationId: (int) $order->organization_id,
                warehouseId: (int) $order->warehouse_id,
                productId: (int) $item->product_id,
                pendingChange: -((int) $item->quantity),
                actorId: $actorId,
                movementType: InventoryMovementType::RELEASE,
                refType: 'order',
                refId: (int) $order->id,
                reasonCode: $reasonCode,
                reasonNote: $reasonNote,
                validateAvailable: false,
            );
        }
    }

    public function consumeOnHandover(Order $order, int $actorId): void
    {
        if ((int) $order->warehouse_id <= 0) {
            throw new \RuntimeException(__('telesale.messages.warehouse_required'));
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;
            $stock = $this->lockStockRow((int) $order->warehouse_id, (int) $item->product_id);
            $available = (int) $stock->quantity;

            if ($available < $qty) {
                throw new \RuntimeException(__('warehouse.ticket.errors.insufficient_stock_for_product', ['id' => $item->product_id]));
            }

            $pendingBefore = (int) $stock->pending_quantity;
            $pendingAfter = max(0, $pendingBefore - $qty);
            $quantityBefore = (int) $stock->quantity;
            $quantityAfter = $quantityBefore - $qty;

            $stock->update([
                'quantity' => $quantityAfter,
                'pending_quantity' => $pendingAfter,
            ]);

            $this->recordMovement([
                'organization_id' => (int) $order->organization_id,
                'warehouse_id' => (int) $order->warehouse_id,
                'product_id' => (int) $item->product_id,
                'ref_type' => 'order',
                'ref_id' => (int) $order->id,
                'movement_type' => InventoryMovementType::CONSUME->value,
                'quantity_before' => $quantityBefore,
                'quantity_change' => -$qty,
                'quantity_after' => $quantityAfter,
                'pending_before' => $pendingBefore,
                'pending_change' => -$qty,
                'pending_after' => $pendingAfter,
                'reason_code' => 'order_handover',
                'reason_note' => null,
                'actor_id' => $actorId,
                'occurred_at' => now(),
            ]);
        }
    }

    public function restockOnReturn(Order $order, array $items, int $actorId, string $reasonCode, ?string $reasonNote = null): void
    {
        if ((int) $order->warehouse_id <= 0) {
            throw new \RuntimeException(__('telesale.messages.warehouse_required'));
        }

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $this->increaseStock(
                organizationId: (int) $order->organization_id,
                warehouseId: (int) $order->warehouse_id,
                productId: $productId,
                quantity: $qty,
                actorId: $actorId,
                movementType: InventoryMovementType::RETURN_IN,
                refType: 'order',
                refId: (int) $order->id,
                reasonCode: $reasonCode,
                reasonNote: $reasonNote,
            );
        }
    }

    protected function increaseStock(
        int $organizationId,
        int $warehouseId,
        int $productId,
        int $quantity,
        int $actorId,
        InventoryMovementType $movementType,
        string $refType,
        int $refId,
        ?string $reasonCode,
        ?string $reasonNote,
    ): void {
        $stock = $this->lockStockRow($warehouseId, $productId);

        $before = (int) $stock->quantity;
        $after = $before + $quantity;

        $stock->update(['quantity' => $after]);

        $this->recordMovement([
            'organization_id' => $organizationId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'movement_type' => $movementType->value,
            'quantity_before' => $before,
            'quantity_change' => $quantity,
            'quantity_after' => $after,
            'pending_before' => (int) $stock->pending_quantity,
            'pending_change' => 0,
            'pending_after' => (int) $stock->pending_quantity,
            'reason_code' => $reasonCode,
            'reason_note' => $reasonNote,
            'actor_id' => $actorId,
            'occurred_at' => now(),
        ]);
    }

    protected function decreaseStock(
        int $organizationId,
        int $warehouseId,
        int $productId,
        int $quantity,
        int $actorId,
        InventoryMovementType $movementType,
        string $refType,
        int $refId,
        ?string $reasonCode,
        ?string $reasonNote,
    ): void {
        $stock = $this->lockStockRow($warehouseId, $productId);

        $available = (int) $stock->quantity - (int) $stock->pending_quantity;
        if ($available < $quantity) {
            throw new \RuntimeException(__('warehouse.ticket.errors.insufficient_stock_for_product', ['id' => $productId]));
        }

        $before = (int) $stock->quantity;
        $after = $before - $quantity;

        $stock->update(['quantity' => $after]);

        $this->recordMovement([
            'organization_id' => $organizationId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'movement_type' => $movementType->value,
            'quantity_before' => $before,
            'quantity_change' => -$quantity,
            'quantity_after' => $after,
            'pending_before' => (int) $stock->pending_quantity,
            'pending_change' => 0,
            'pending_after' => (int) $stock->pending_quantity,
            'reason_code' => $reasonCode,
            'reason_note' => $reasonNote,
            'actor_id' => $actorId,
            'occurred_at' => now(),
        ]);
    }

    protected function changePending(
        int $organizationId,
        int $warehouseId,
        int $productId,
        int $pendingChange,
        int $actorId,
        InventoryMovementType $movementType,
        string $refType,
        int $refId,
        ?string $reasonCode,
        ?string $reasonNote,
        bool $validateAvailable,
    ): void {
        $stock = $this->lockStockRow($warehouseId, $productId);

        $pendingBefore = (int) $stock->pending_quantity;
        $pendingAfter = $pendingBefore + $pendingChange;

        if ($pendingAfter < 0) {
            $pendingAfter = 0;
        }

        if ($validateAvailable && $pendingChange > 0) {
            $available = (int) $stock->quantity - $pendingBefore;
            if ($available < $pendingChange) {
                throw new \RuntimeException(__('warehouse.ticket.errors.insufficient_stock_for_product', ['id' => $productId]));
            }
        }

        $stock->update(['pending_quantity' => $pendingAfter]);

        $this->recordMovement([
            'organization_id' => $organizationId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'movement_type' => $movementType->value,
            'quantity_before' => (int) $stock->quantity,
            'quantity_change' => 0,
            'quantity_after' => (int) $stock->quantity,
            'pending_before' => $pendingBefore,
            'pending_change' => $pendingChange,
            'pending_after' => $pendingAfter,
            'reason_code' => $reasonCode,
            'reason_note' => $reasonNote,
            'actor_id' => $actorId,
            'occurred_at' => now(),
        ]);
    }

    protected function lockStockRow(int $warehouseId, int $productId): ProductWarehouse
    {
        $stock = $this->productWarehouseRepository->query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            $stock = $this->productWarehouseRepository->create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => 0,
                'pending_quantity' => 0,
            ]);

            $stock = $this->productWarehouseRepository->query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $stock;
    }

    protected function recordMovement(array $data): void
    {
        $this->inventoryMovementRepository->create($data);
    }

    protected function logTicketAction(
        InventoryTicket $ticket,
        string $action,
        int $oldStatus,
        int $newStatus,
        int $actorId,
        ?string $reasonCode,
        ?string $reasonNote,
        array $metadata,
    ): void {
        $ticket->logs()->create([
            'product_id' => null,
            'reason' => $reasonCode,
            'note' => $reasonNote,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'metadata_json' => $metadata,
            'user_id' => $actorId,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    protected function logDetailMovement(
        InventoryTicket $ticket,
        int $productId,
        string $action,
        int $actorId,
        ?string $reasonCode,
        ?string $reasonNote,
        int $quantity,
    ): void {
        $ticket->logs()->create([
            'product_id' => $productId,
            'reason' => $reasonCode,
            'note' => $reasonNote,
            'action' => $action,
            'old_status' => $ticket->status,
            'new_status' => $ticket->status,
            'metadata_json' => [
                'quantity' => $quantity,
                'ticket_type' => $ticket->type,
            ],
            'user_id' => $actorId,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }
}

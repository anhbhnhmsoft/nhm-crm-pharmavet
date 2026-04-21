<?php

namespace App\Services\Warehouse;

use App\Common\Constants\Order\OrderStatus;
use App\Core\ServiceReturn;
use App\Repositories\OrderRepository;
use App\Services\ExportService;

class OrderExportTicketPrintService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected ExportService $exportService,
    ) {
    }

    public function generatePdf(int $orderId, ?int $organizationId = null): ServiceReturn
    {
        $order = $this->orderRepository->query()
            ->with([
                'customer:id,username,phone,address',
                'warehouse:id,name,address,phone',
                'items.product:id,name,sku,unit',
                'createdBy:id,name',
            ])
            ->whereKey($orderId)
            ->when($organizationId !== null, fn($query) => $query->where('organization_id', $organizationId))
            ->first();

        if (!$order) {
            return ServiceReturn::error(__('common.error.data_not_found'));
        }

        if (!in_array((int) $order->status, [
            OrderStatus::CONFIRMED->value,
            OrderStatus::SHIPPING->value,
            OrderStatus::COMPLETED->value,
        ], true)) {
            return ServiceReturn::error(__('warehouse.order.print.not_printable'));
        }

        if ($order->items->isEmpty()) {
            return ServiceReturn::error(__('warehouse.order.print.no_items'));
        }

        $pdfContent = $this->exportService->generatePdfContent('pdf.order-export-ticket', [
            'order' => $order,
            'printedAt' => now(),
            'shippingAddress' => $order->shipping_address ?: ($order->customer?->address ?? ''),
            'totalQuantity' => (int) $order->items->sum(fn($item) => (int) $item->quantity),
        ]);

        return ServiceReturn::success(data: [
            'content' => $pdfContent,
            'filename' => 'phieu-xuat-kho-' . $order->code . '.pdf',
        ]);
    }
}

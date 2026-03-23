<?php

namespace App\Services\Telesale;

use App\Common\Constants\Order\OrderStatus;
use App\Repositories\CustomerRepository;

class Customer360Service
{

    public function __construct(
        private CustomerRepository $customerRepository,
    ) {
    }

    public function getCustomer360Snapshot(int $customerId): array
    {
        $customer = $this->customerRepository->query()
            ->with([
                'interactions.user',
                'orders.items.product',
                'orders' => fn($query) => $query->latest('created_at'),
            ])
            ->findOrFail($customerId);

        $orders = $customer->orders;

        $totalRevenue = (float) $orders
            ->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::SHIPPING->value, OrderStatus::COMPLETED->value])
            ->sum('total_amount');

        $debtAmount = (float) $orders
            ->whereIn('status', [OrderStatus::PENDING->value, OrderStatus::CONFIRMED->value, OrderStatus::SHIPPING->value])
            ->sum('collect_amount');

        return [
            'customer' => $customer,
            'interactions' => $customer->interactions()->with('user')->latest('interacted_at')->take(100)->get(),
            'orders' => $orders,
            'total_revenue' => $totalRevenue,
            'debt_amount' => $debtAmount,
            'latest_order_status' => optional($orders->first())->status,
        ];
    }
}

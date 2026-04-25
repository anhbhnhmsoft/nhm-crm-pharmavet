<?php

namespace App\Services\Telesale;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Shipping\ProviderShipping;
use App\Common\Constants\User\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\ShippingShop;
use App\Models\Warehouse;
use App\Repositories\ShippingConfigRepository;
use App\Services\GHNService;
use App\Services\OrderService;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TelesaleFinalizeOrderService
{
    public function __construct(
        protected OrderFinanceService $orderFinanceService,
        protected ShippingConfigRepository $shippingConfigRepository,
    ) {
    }

    public function getLatestOrder($record): ?Order
    {
        if ($record->relationLoaded('customerOperationsLatestOrder')) {
            return $record->getRelation('customerOperationsLatestOrder');
        }

        $latestOrder = $record->orders()->latest()->first();
        $record->setRelation('customerOperationsLatestOrder', $latestOrder);

        return $latestOrder;
    }

    public function getLatestOrderStatus($record): ?int
    {
        return $this->getLatestOrder($record)?->status;
    }

    public function getEditableOrder($record): ?Order
    {
        return Order::query()
            ->with('items')
            ->where('customer_id', $record->id)
            ->whereIn('status', [OrderStatus::PENDING->value, OrderStatus::CONFIRMED->value])
            ->latest()
            ->first();
    }

    public function hasEditableOrder($record): bool
    {
        return (bool) $this->getEditableOrder($record);
    }

    public function isFinalizeOrderLockedForSale($record): bool
    {
        if ((int) (Auth::user()?->role ?? 0) !== UserRole::SALE->value) {
            return false;
        }

        return in_array(
            (int) ($this->getLatestOrderStatus($record) ?? 0),
            [OrderStatus::SHIPPING->value, OrderStatus::COMPLETED->value],
            true
        );
    }

    public function mountForm($form, $record): void
    {
        $order = $this->getEditableOrder($record);

        if ($order) {
            $data = $order->toArray();
            $data['items'] = $order->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total,
                ];
            })->toArray();
            $data['status_action'] = $order->status;
            $data['weight'] = $order->weight;
            $data['length'] = $order->length;
            $data['width'] = $order->width;
            $data['height'] = $order->height;
            $data['ghn_payment_type_id'] = $order->ghn_payment_type_id;
            $data['ghn_service_type_id'] = $order->ghn_service_type_id;
            $data['ghn_content'] = $order->ghn_content;
            $data['insurance_value'] = $order->insurance_value;
            $data['ghn_cod_failed_amount'] = $order->ghn_cod_failed_amount;
            $data['ghn_pick_station_id'] = $order->ghn_pick_station_id;
            $data['ghn_deliver_station_id'] = $order->ghn_deliver_station_id;
            $data['ghn_province_id'] = $order->ghn_province_id;
            $data['ghn_district_id'] = $order->ghn_district_id;
            $data['ghn_ward_code'] = $order->ghn_ward_code;
            $data['client_order_code'] = $order->code;

            $form->fill($data);

            return;
        }

        $form->fill([
            'username' => $record->username,
            'phone' => $record->phone,
            'address' => $record->address,
            'province_id' => $record->province_id,
            'district_id' => $record->district_id,
            'ward_id' => $record->ward_id,
            'organization_id' => $record->organization_id,
            'client_order_code' => 'ORD-' . time(),
            'status_action' => OrderStatus::CONFIRMED->value,
        ]);
    }

    public function getWarehouseOptions(?int $organizationId): array
    {
        if (($organizationId ?? 0) <= 0) {
            return [];
        }

        return Warehouse::query()
            ->where('organization_id', $organizationId)
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getWarehouseProductOptions(?int $organizationId, ?int $warehouseId): array
    {
        if (($organizationId ?? 0) <= 0 || ($warehouseId ?? 0) <= 0) {
            return [];
        }

        $products = Product::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $stocks = ProductWarehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $products->pluck('id'))
            ->get(['product_id', 'quantity', 'pending_quantity'])
            ->keyBy('product_id');

        return $products
            ->mapWithKeys(function (Product $product) use ($stocks) {
                $stock = $stocks->get($product->id);
                $availableStock = max(0, (int) ($stock?->quantity ?? 0) - (int) ($stock?->pending_quantity ?? 0));

                return [
                    $product->id => sprintf(
                        '%s (%s: %d)',
                        $product->name,
                        __('warehouse.reports.available_stock'),
                        $availableStock
                    ),
                ];
            })
            ->all();
    }

    public function getAvailableStock(int $warehouseId, int $productId): int
    {
        if ($warehouseId <= 0 || $productId <= 0) {
            return 0;
        }

        $stock = ProductWarehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first(['quantity', 'pending_quantity']);

        return max(0, (int) ($stock?->quantity ?? 0) - (int) ($stock?->pending_quantity ?? 0));
    }

    public function syncOrderLogistics(Set $set, array $items): void
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->map(fn($productId) => (int) $productId)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            $set('weight', 0);
            $set('length', 0);
            $set('width', 0);
            $set('height', 0);
            return;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'weight', 'length', 'width', 'height'])
            ->keyBy('id');

        $totalWeight = 0;
        $maxLength = 0;
        $maxWidth = 0;
        $totalHeight = 0;

        foreach ($items as $item) {
            $product = $products->get((int) ($item['product_id'] ?? 0));

            if (! $product) {
                continue;
            }

            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $totalWeight += (int) ($product->weight ?? 200) * $quantity;
            $maxLength = max($maxLength, (int) ($product->length ?? 10));
            $maxWidth = max($maxWidth, (int) ($product->width ?? 10));
            $totalHeight += (int) ($product->height ?? 5) * $quantity;
        }

        $set('weight', $totalWeight);
        $set('length', $maxLength);
        $set('width', $maxWidth);
        $set('height', $totalHeight);
    }

    public function resetOrderSummary(Set $set): void
    {
        $set('total_amount_temp', 0);
        $set('total_discount_display', $this->formatCurrency(0));
        $set('cod_amount', 0);
    }

    public function recalculateOrderSummary(Set $set, Get $get): void
    {
        $inputs = $this->getOrderFinanceInputs($get);
        $results = $this->orderFinanceService->calculatePreview($inputs);

        $set('total_amount_temp', $inputs['product_total']);
        $set('total_discount_display', $this->formatCurrency($results['total_discount']));
        $set('cod_amount', $results['collect_amount']);
    }

    public function getMaxAllowedDeposit(Get $get): float
    {
        $results = $this->orderFinanceService->calculatePreview($this->getOrderFinanceInputs($get, 0));

        return (float) ($results['gross_total'] ?? 0);
    }

    public function validateFinalizeOrderData(array $data, $record): void
    {
        $messages = [];
        $shippingMethod = trim((string) ($data['shipping_method'] ?? ''));
        $requiredNote = trim((string) ($data['required_note'] ?? ''));
        $clientOrderCode = trim((string) ($data['client_order_code'] ?? ''));
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);

        if ($shippingMethod === '') {
            $messages['shipping_method'] = __('common.error.required');
        } elseif (! array_key_exists($shippingMethod, ProviderShipping::getOptions())) {
            $messages['shipping_method'] = __('common.error.in', ['attribute' => __('warehouse.order.form.shipping_method')]);
        }

        if ($requiredNote === '') {
            $messages['required_note'] = __('common.error.required');
        } elseif (! array_key_exists($requiredNote, RequiredNote::getOptions())) {
            $messages['required_note'] = __('common.error.in', ['attribute' => __('warehouse.order.form.required_note')]);
        }

        if ($clientOrderCode === '') {
            $messages['client_order_code'] = __('common.error.required');
        }

        if ($warehouseId <= 0) {
            $messages['warehouse_id'] = __('telesale.messages.warehouse_required');
        }

        $items = $this->normalizeFinalizeItems($data['items'] ?? []);
        if ($items === []) {
            $messages['items'] = __('common.error.min.array', ['min' => 1]);
        }

        $normalizedItems = collect($items)->map(function (array $item, int $index) {
            return [
                'index' => $index,
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ];
        });

        foreach ($normalizedItems as $item) {
            if ($item['product_id'] <= 0) {
                $messages["items.{$item['index']}.product_id"] = __('common.error.required');
            }

            if ($item['quantity'] <= 0) {
                $messages["items.{$item['index']}.quantity"] = __('common.error.min_value', ['min' => 1]);
            }
        }

        $existingOrder = Order::query()
            ->with('items')
            ->where('customer_id', $record->id)
            ->whereIn('status', [OrderStatus::PENDING->value, OrderStatus::CONFIRMED->value])
            ->latest()
            ->first();

        if ($warehouseId > 0) {
            foreach ($normalizedItems->groupBy('product_id') as $productId => $rows) {
                $productId = (int) $productId;

                if ($productId <= 0) {
                    continue;
                }

                $requiredQuantity = (int) $rows->sum('quantity');
                if ($requiredQuantity <= 0) {
                    continue;
                }

                $availableQuantity = $this->getAvailableStock($warehouseId, $productId)
                    + $this->getExistingReservedQuantity($existingOrder, $warehouseId, $productId);

                if ($requiredQuantity > $availableQuantity) {
                    $productName = Product::query()->find($productId)?->name ?? '#' . $productId;
                    $messages["items.{$rows->first()['index']}.quantity"] = __('telesale.messages.insufficient_stock', [
                        'product' => $productName,
                    ]);
                }
            }
        }

        foreach ([
            'weight' => 20000,
            'length' => 200,
            'width' => 200,
            'height' => 200,
        ] as $field => $maxValue) {
            $value = $data[$field] ?? null;

            if ($value === null || $value === '') {
                $messages[$field] = __('common.error.required');
                continue;
            }

            if (! is_numeric($value)) {
                $messages[$field] = __('common.error.numeric');
                continue;
            }

            $numericValue = (float) $value;

            if ($numericValue < 1) {
                $messages[$field] = __('common.error.min_value', ['min' => 1]);
                continue;
            }

            if ($numericValue > $maxValue) {
                $messages[$field] = __('common.error.max_value', ['max' => $maxValue]);
            }
        }

        $productTotal = $normalizedItems->sum(function (array $item) use ($items) {
            $row = $items[$item['index']] ?? [];

            return max(0, (int) ($item['quantity'] ?? 0)) * (float) ($row['price'] ?? 0);
        });

        $preview = $this->orderFinanceService->calculatePreview([
            'product_total' => $productTotal,
            'discount' => $data['discount'] ?? 0,
            'ck1' => $data['ck1'] ?? 0,
            'ck2' => $data['ck2'] ?? 0,
            'shipping_fee' => $data['shipping_fee'] ?? 0,
            'cod_fee' => $data['cod_fee'] ?? 0,
            'deposit' => $data['deposit'] ?? 0,
            'cod_support_amount' => $data['cod_support_amount'] ?? 0,
        ]);

        if ((float) ($data['deposit'] ?? 0) > (float) ($preview['gross_total'] ?? 0)) {
            $messages['deposit'] = __('telesale.messages.deposit_exceeds_total');
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected function notifyFinalizeOrderValidationException(ValidationException $exception): void
    {
        $message = collect($exception->errors())
            ->filter(fn(array $messages, string $key) => str_starts_with($key, 'items.') && str_ends_with($key, '.quantity'))
            ->flatten()
            ->filter(fn($value) => is_string($value) && filled($value))
            ->map(fn(string $value) => trim($value))
            ->first();

        if (! $message) {
            return;
        }

        Notification::make()
            ->title($message)
            ->danger()
            ->send();
    }

    public function handleFinalizeAction(array $data, $record, OrderService $orderService): void
    {
        Log::info('finalize_order: Action triggered', [
            'customer_id' => $record->id,
            'user_id' => Auth::id(),
            'data_keys' => array_keys($data),
        ]);

        $data['items'] = $this->normalizeFinalizeItems($data['items'] ?? []);
        $data['customer_id'] = $record->id;
        $data['organization_id'] = $data['organization_id'] ?? $record->organization_id;
        $data['code'] = $data['client_order_code'];
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        try {
            $this->validateFinalizeOrderData($data, $record);
        } catch (ValidationException $exception) {
            $this->notifyFinalizeOrderValidationException($exception);

            throw $exception;
        }

        $isUpdatingOrder = $this->hasEditableOrder($record);
        $result = $orderService->finalizeOrder($data);

        Log::info('finalize_order: Service result', [
            'is_success' => $result->isSuccess(),
            'message' => $result->getMessage(),
        ]);

        if ($result->isSuccess()) {
            Notification::make()
                ->title(__($isUpdatingOrder ? 'telesale_action.update_order_success' : 'telesale_action.finalize_order_success'))
                ->success()
                ->send();
            return;
        }

        if ($result->getMessage() === __('telesale.messages.deposit_exceeds_total')) {
            throw ValidationException::withMessages([
                'deposit' => $result->getMessage(),
            ]);
        }

        Notification::make()->title($result->getMessage())->danger()->send();
    }

    protected function normalizeFinalizeItems(array $items): array
    {
        return collect($items)
            ->filter(function ($item): bool {
                if (! is_array($item)) {
                    return false;
                }

                return (int) ($item['product_id'] ?? 0) > 0;
            })
            ->values()
            ->all();
    }

    public function canCancelFinalize($record): bool
    {
        $order = Order::query()->where('customer_id', $record->id)->latest()->first();

        return $order && (int) $order->status === OrderStatus::CONFIRMED->value;
    }

    public function cancelFinalize($record): void
    {
        $order = Order::query()->where('customer_id', $record->id)->latest()->first();

        if ($order && (int) $order->status === OrderStatus::CONFIRMED->value) {
            $order->update(['status' => OrderStatus::PENDING->value]);
            Notification::make()->title(__('warehouse.order.form.cancel_finalize'))->success()->send();
        }
    }

    public function getGhnProvinceOptions(?int $organizationId): array
    {
        if (! $organizationId) {
            return [];
        }

        try {
            $ghn = new GHNService($this->shippingConfigRepository, (int) $organizationId);
            $provinces = $ghn->getProvinces();

            return collect($provinces)->pluck('ProvinceName', 'ProvinceID')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function getGhnDistrictOptions(?int $organizationId, ?int $provinceId): array
    {
        if (! $organizationId || ! $provinceId) {
            return [];
        }

        try {
            $ghn = new GHNService($this->shippingConfigRepository, (int) $organizationId);
            $districts = $ghn->getDistricts((int) $provinceId);

            return collect($districts)->pluck('DistrictName', 'DistrictID')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function getGhnWardOptions(?int $organizationId, ?int $districtId): array
    {
        if (! $organizationId || ! $districtId) {
            return [];
        }

        try {
            $ghn = new GHNService($this->shippingConfigRepository, (int) $organizationId);
            $wards = $ghn->getWards((int) $districtId);

            return collect($wards)->pluck('WardName', 'WardCode')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function getShippingShopOptions(?int $organizationId): array
    {
        if (! $organizationId) {
            return [];
        }

        return ShippingShop::query()
            ->where('organization_id', (int) $organizationId)
            ->pluck('name', 'shop_id')
            ->toArray();
    }

    protected function getOrderFinanceInputs(Get $get, ?float $depositOverride = null): array
    {
        $items = $get('items') ?? [];
        $productTotal = collect($items)->sum(function ($item) {
            return ($item['quantity'] ?? 0) * ($item['price'] ?? 0);
        });

        return [
            'product_total' => $productTotal,
            'discount' => $get('discount') ?? 0,
            'ck1' => $get('ck1') ?? 0,
            'ck2' => $get('ck2') ?? 0,
            'shipping_fee' => $get('shipping_fee') ?? 0,
            'cod_fee' => $get('cod_fee') ?? 0,
            'deposit' => $depositOverride ?? ($get('deposit') ?? 0),
            'cod_support_amount' => $get('cod_support_amount') ?? 0,
        ];
    }

    protected function getExistingReservedQuantity(?Order $existingOrder, int $warehouseId, int $productId): int
    {
        if (
            ! $existingOrder
            || (int) $existingOrder->status !== OrderStatus::CONFIRMED->value
            || (int) $existingOrder->warehouse_id !== $warehouseId
            || ! $existingOrder->relationLoaded('items')
        ) {
            return 0;
        }

        return (int) $existingOrder->items
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    protected function formatCurrency(float|int $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' VNĐ';
    }
}

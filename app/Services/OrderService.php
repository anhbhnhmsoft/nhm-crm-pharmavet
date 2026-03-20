<?php

namespace App\Services;

use App\Common\Constants\Order\GhnOrderStatus;
use App\Common\Constants\Order\OrderStatus;
use App\Core\ServiceReturn;
use App\Jobs\ProcessGHNOrderJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\ShippingConfig;
use App\Models\ShippingConfigForWarehouse;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected GHNService $ghnService
    ) {}

    public function postOrder(Order $order, array $data): ServiceReturn
    {
        try {
            // Update shipping fee if changed
            if (isset($data['shipping_fee']) && $data['shipping_fee'] != $order->shipping_fee) {
                $diff = $data['shipping_fee'] - $order->shipping_fee;
                $order->shipping_fee = $data['shipping_fee'];
                $order->total_amount += $diff;
                $order->save();
            }

            // Update other fields if provided
            if (isset($data['weight'])) {
                $order->weight = $data['weight'];
            }
            if (isset($data['length'])) {
                $order->length = $data['length'];
            }
            if (isset($data['width'])) {
                $order->width = $data['width'];
            }
            if (isset($data['height'])) {
                $order->height = $data['height'];
            }
            if (isset($data['insurance_value'])) {
                $order->insurance_value = $data['insurance_value'];
            }
            if (isset($data['ghn_service_type_id'])) {
                $order->ghn_service_type_id = $data['ghn_service_type_id'];
            }
            if (isset($data['ghn_payment_type_id'])) {
                $order->ghn_payment_type_id = $data['ghn_payment_type_id'];
            }
            if (isset($data['required_note'])) {
                $order->required_note = $data['required_note'];
            }

            $order->save();

            // Dispatch job to process GHN order
            \App\Jobs\ProcessGHNOrderJob::dispatch($order, 'post', $data)->onQueue('post_ghn_order');

            return ServiceReturn::success(__('order.message.post_order_queued'));
        } catch (\Exception $e) {
            return ServiceReturn::error($e->getMessage());
        }
    }

    public function cancelOrder(Order $order): ServiceReturn
    {
        try {
            // Dispatch job to cancel GHN order
            \App\Jobs\ProcessGHNOrderJob::dispatch($order, 'cancel')->onQueue('cancel_ghn_order');

            return ServiceReturn::success(__('order.message.cancel_order_queued'));
        } catch (\Exception $e) {
            return ServiceReturn::error($e->getMessage());
        }
    }

    public function finalizeOrder(array $data): ServiceReturn
    {
        Log::info('OrderService: finalizeOrder triggered', ['data_keys' => array_keys($data)]);
        DB::beginTransaction();
        try {
            $existingOrder = $this->orderRepository->query()->where('customer_id', $data['customer_id'])
                ->latest()
                ->first();

            // Calculate totals
            $items = $data['items'] ?? [];
            $productTotal = collect($items)->sum(fn($item) => ($item['quantity'] ?? 0) * ($item['price'] ?? 0));

            $ck1 = $data['ck1'] ?? 0;
            $ck2 = $data['ck2'] ?? 0;
            $orderDiscount = $data['discount'] ?? 0;
            $productDiscount = $productTotal * ($ck1 + $ck2) / 100;
            $totalDiscount = $productDiscount + $orderDiscount;
            $shippingFee = (float) ($data['shipping_fee'] ?? 0);
            $codFee = (float) ($data['cod_fee'] ?? 0);
            $deposit = (float) ($data['deposit'] ?? 0);
            $totalAmount = $productTotal - $totalDiscount + $shippingFee + $codFee;

            if ($deposit > $totalAmount) {
                throw new \RuntimeException(__('telesale.messages.deposit_exceeds_total'));
            }

            $warehouseId = (int) ($data['warehouse_id'] ?? 0);
            if ($warehouseId <= 0) {
                throw new \RuntimeException(__('telesale.messages.warehouse_required'));
            }

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $requiredQty = (int) ($item['quantity'] ?? 0);

                if ($productId <= 0 || $requiredQty <= 0) {
                    continue;
                }

                $stock = ProductWarehouse::query()
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->first();

                $availableQty = (int) (($stock?->quantity ?? 0) - ($stock?->pending_quantity ?? 0));
                if ($availableQty < $requiredQty) {
                    $productName = Product::find($productId)?->name ?? '#' . $productId;
                    throw new \RuntimeException(__('telesale.messages.insufficient_stock', ['product' => $productName]));
                }
            }

            // Create or Update Order
            $orderData = [
                'organization_id' => $existingOrder?->organization_id ?? $data['organization_id'],
                'customer_id' => $existingOrder?->customer_id ?? $data['customer_id'],
                'status' => $data['status_action'],
                'total_amount' => $totalAmount,
                'discount' => $totalDiscount, // Saving total discount
                'shipping_fee' => $shippingFee,
                'shipping_method' => $data['shipping_method'] ?? null,
                'shipping_address' => $data['address'] ?? null,
                'province_id' => $data['province_id'] ?? null,
                'district_id' => $data['district_id'] ?? null,
                'ward_id' => $data['ward_id'] ?? null,
                'deposit' => $deposit,
                'cod_fee' => $codFee,
                'ck1' => $ck1,
                'ck2' => $ck2,
                'warehouse_id' => $warehouseId,
                'required_note' => $data['required_note'] ?? null,
                'weight' => $data['weight'] ?? null,
                'length' => $data['length'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'insurance_value' => $data['insurance_value'] ?? 0,
                'ghn_payment_type_id' => $data['ghn_payment_type_id'] ?? null,
                'ghn_service_type_id' => $data['ghn_service_type_id'] ?? null,
                'ghn_content' => $data['ghn_content'] ?? null,
                'ghn_cod_failed_amount' => $data['ghn_cod_failed_amount'] ?? 0,
                'ghn_pick_station_id' => $data['ghn_pick_station_id'] ?? null,
                'ghn_deliver_station_id' => $data['ghn_deliver_station_id'] ?? null,
                'ghn_province_id' => $data['ghn_province_id'] ?? null,
                'ghn_district_id' => $data['ghn_district_id'] ?? null,
                'ghn_ward_code' => $data['ghn_ward_code'] ?? null,
                'updated_by' => $data['updated_by'],
            ];

            if ($existingOrder && in_array($existingOrder->status, [OrderStatus::PENDING->value, OrderStatus::CONFIRMED->value])) {
                $existingOrder->update($orderData);
                $existingOrder->items()->delete();
                $order = $existingOrder;
            } else {
                $orderData['code'] = $data['code'] ?? ('ORD-' . time());
                $orderData['created_by'] = $data['created_by'];
                $order = $this->orderRepository->create($orderData);
            }

            // Save items
            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => ($item['quantity'] * $item['price']),
                ]);
            }

            // Auto-post to GHN if confirmed
            if ($order->status == OrderStatus::CONFIRMED->value) {
                ProcessGHNOrderJob::dispatch($order, 'post')->onQueue('post_ghn_order');
            }

            DB::commit();
            return ServiceReturn::success();
        } catch (\Exception $e) {
            DB::rollBack();
            return ServiceReturn::error($e->getMessage());
        }
    }

    public function processPostOrder(Order $order): void
    {
        DB::beginTransaction();
        try {
            // Get shipping config
            $config = $this->getShippingConfig($order);

            $this->ghnService->setToken($config['token'])->setShopId($config['shop_id']);

            // Prepare order items
            $items = $order->items->map(function ($item) {
                return [
                    'name' => $item->product->name,
                    'code' => $item->product->sku ?? $item->product->barcode,
                    'quantity' => $item->quantity,
                    'price' => (int) $item->price,
                    'weight' => (int) ($item->product->weight ?? 200),
                    'length' => (int) ($item->product->length ?? 10),
                    'width' => (int) ($item->product->width ?? 10),
                    'height' => (int) ($item->product->height ?? 5),
                ];
            })->toArray();

            // Calculate total weight
            $totalWeight = $order->weight ?? collect($items)->sum('weight');

            // Prepare GHN API parameters
            $params = [
                'payment_type_id' => $order->ghn_payment_type_id ?? 2, // 1: Seller pays, 2: Buyer pays (COD)
                'note' => $order->note,
                'required_note' => $order->required_note ?? 'KHONGCHOXEMHANG',
                'to_name' => $order->customer->username,
                'to_phone' => $order->customer->phone,
                'to_address' => $order->shipping_address ?? $order->customer->address,
                'to_ward_code' => $this->getWardCode($order),
                'to_district_id' => $this->getDistrictCode($order),
                'cod_amount' => (int) ($order->total_amount - $order->deposit),
                'items' => $items,
                'service_type_id' => $order->ghn_service_type_id ?? 2, // 2: Standard
                'weight' => (int) min(20000, ($order->weight ?? $totalWeight)),
                'length' => (int) min(200, ($order->length ?? 10)),
                'width' => (int) min(200, ($order->width ?? 10)),
                'height' => (int) min(200, ($order->height ?? 5)),
                'insurance_value' => (int) $order->insurance_value,
                'coupon' => $order->coupon,
                'client_order_code' => $order->code,
                'content' => $order->ghn_content ?? $order->note,
                'cod_failed_amount' => (int) $order->ghn_cod_failed_amount,
                'pick_station_id' => $order->ghn_pick_station_id,
                'deliver_station_id' => $order->ghn_deliver_station_id,
            ];

            // Remove null values
            $params = array_filter($params, fn($value) => !is_null($value));

            // Call GHN API
            $result = $this->ghnService->createOrder($params);

            // Update order with GHN response
            $order->update([
                'status' => OrderStatus::SHIPPING->value,
                'ghn_order_code' => $result['order_code'] ?? null,
                'ghn_expected_delivery_time' => isset($result['expected_delivery_time'])
                    ? date('Y-m-d H:i:s', strtotime($result['expected_delivery_time']))
                    : null,
                'ghn_total_fee' => $result['total_fee'] ?? null,
                'ghn_response' => json_encode($result),
                'ghn_status' => GhnOrderStatus::READY_TO_PICK->value,
                'ghn_posted_at' => now(),
            ]);

            DB::commit();

            \Illuminate\Support\Facades\Log::info('Order posted to GHN successfully', [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'ghn_order_code' => $result['order_code'] ?? null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Update order with error status
            $order->update([
                'ghn_response' => json_encode([
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toDateTimeString(),
                ]),
            ]);

            throw $e;
        }
    }

    public function calculateShippingFee(Order $order, array $data): ServiceReturn
    {
        try {
            // Get shipping config
            $config = $this->getShippingConfig($order);

            $this->ghnService->setToken($config['token'])->setShopId($config['shop_id']);

            // Calculate total weight
            $totalWeight = $data['weight'] ?? ($order->weight ?? 0);
            if ($totalWeight == 0) {
                // Fallback to items weight if not provided
                $items = $order->items;
                $totalWeight = $items->sum(fn($item) => ($item->product->weight ?? 200) * $item->quantity);
            }

            // Prepare params
            $params = [
                'service_type_id' => $data['ghn_service_type_id'] ?? 2,
                'insurance_value' => $data['insurance_value'] ?? 0,
                'coupon' => null, // Add coupon if needed
                'to_ward_code' => $this->getWardCode($order),
                'to_district_id' => $this->getDistrictCode($order),
                'weight' => (int) $totalWeight,
                'length' => (int) ($data['length'] ?? $order->length ?? 0),
                'width' => (int) ($data['width'] ?? $order->width ?? 0),
                'height' => (int) ($data['height'] ?? $order->height ?? 0),
            ];

            // Remove null values
            $params = array_filter($params, fn($value) => !is_null($value));

            $result = $this->ghnService->calculateFee($params);

            return ServiceReturn::success($result);
        } catch (\Exception $e) {
            return ServiceReturn::error($e->getMessage());
        }
    }

    protected function getShippingConfig(Order $order): array
    {
        // 1. Check Warehouse Config
        if ($order->warehouse_id) {
            $warehouseConfig = ShippingConfigForWarehouse::where('warehouse_id', $order->warehouse_id)
                ->where('organization_id', $order->organization_id)
                ->first();

            if ($warehouseConfig && $warehouseConfig->api_token && $warehouseConfig->store_id) {
                return [
                    'token' => $warehouseConfig->api_token,
                    'shop_id' => $warehouseConfig->store_id,
                ];
            }
        }

        // 2. Check Organization Config
        $orgConfig = ShippingConfig::where('organization_id', $order->organization_id)->first();

        if ($orgConfig && $orgConfig->api_token && $orgConfig->default_store_id) {
            return [
                'token' => $orgConfig->api_token,
                'shop_id' => $orgConfig->default_store_id,
            ];
        }

        throw new \Exception(__('filament.shipping.no_config_found'));
    }

    public function processCancelOrder(Order $order): void
    {
        DB::beginTransaction();
        try {
            // Get shipping config
            $config = $this->getShippingConfig($order);

            // Initialize GHN Service
            $ghnService = app(\App\Services\GHNService::class);
            $ghnService->setToken($config['token'])->setShopId($config['shop_id']);

            // Use GHN order code if available, otherwise use client order code
            $orderCode = $order->ghn_order_code ?? $order->code;

            // Call GHN Cancel API
            $result = $ghnService->cancelOrder($orderCode);

            // Generate new order code with -R suffix
            $newCode = $this->generateRefreshedCode($order->code);

            // Update order
            $order->update([
                'status' => OrderStatus::CONFIRMED->value,
                'code' => $newCode,
                'ghn_status' => GhnOrderStatus::CANCEL->value,
                'ghn_cancelled_at' => now(),
                'ghn_response' => json_encode(array_merge(
                    json_decode($order->ghn_response ?? '{}', true),
                    ['cancel_result' => $result]
                )),
            ]);

            DB::commit();

            \Illuminate\Support\Facades\Log::info('Order cancelled on GHN successfully', [
                'order_id' => $order->id,
                'old_code' => $orderCode,
                'new_code' => $newCode,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function getWardCode(Order $order): ?string
    {
        if ($order->ghn_ward_code) {
            return trim($order->ghn_ward_code);
        }

        if (!$order->ward_id) {
            return null;
        }

        $ward = \App\Models\Ward::find($order->ward_id);
        if (!$ward)
            return null;

        // Ưu tiên dùng ghn_code (từ GHN API), fallback về code nội bộ
        return $ward->ghn_code ? trim($ward->ghn_code) : trim($ward->code);
    }

    protected function getDistrictCode(Order $order): ?int
    {
        if ($order->ghn_district_id) {
            return (int) $order->ghn_district_id;
        }

        if (!$order->district_id) {
            return null;
        }

        $district = \App\Models\District::find($order->district_id);
        if (!$district)
            return null;

        // Ưu tiên dùng ghn_id (từ GHN API), fallback về code nội bộ
        return $district->ghn_id ? (int) $district->ghn_id : (int) $district->code;
    }

    protected function generateRefreshedCode(string $currentCode): string
    {
        // Check if code already has -R suffix
        if (preg_match('/-R(\d*)$/', $currentCode, $matches)) {
            // Increment the number after -R
            $number = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 1;
            return preg_replace('/-R\d*$/', '-R' . ($number + 1), $currentCode);
        }

        // Add -R suffix
        return $currentCode . '-R';
    }
}

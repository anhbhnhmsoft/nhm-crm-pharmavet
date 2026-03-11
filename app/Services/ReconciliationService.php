<?php

namespace App\Services;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\Order\GhnOrderStatus;
use App\Core\Logging;
use App\Core\ServiceReturn;
use App\Models\Organization;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ReconciliationRepository;
use App\Repositories\ShippingConfigRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ReconciliationService
{
    public function __construct(
        protected ReconciliationRepository $reconciliationRepository,
        protected OrderRepository $orderRepository,
        protected ExchangeRateRepository $exchangeRateRepository,
        protected ShippingConfigRepository $shippingConfigRepository,
        protected GHNService $ghnService,
    ) {
    }

    /**
     * Đồng bộ đối soát từ GHN.
     */
    public function syncReconciliationFromGHN(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            $config = $this->shippingConfigRepository->query()
                ->where('organization_id', $organizationId)
                ->first();

            if (!$config) {
                return ServiceReturn::error(__('accounting.reconciliation.config_not_found'));
            }

            if (empty($config->api_token) || empty($config->default_store_id)) {
                return ServiceReturn::error(__('accounting.reconciliation.config_incomplete'));
            }

            $this->ghnService->setToken($config->api_token)->setShopId($config->default_store_id);

            $offset = 0;
            $limit = 50;
            $created = 0;
            $updated = 0;
            $isForeignOrganization = $this->isForeignOrganization($organizationId);

            do {
                $orders = $this->ghnService->searchOrders([
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'offset' => $offset,
                    'limit' => $limit,
                ]);

                if (isset($orders['orders']) && is_array($orders['orders'])) {
                    $orderList = $orders['orders'];
                } elseif (isset($orders['data']) && is_array($orders['data'])) {
                    $orderList = $orders['data'];
                } elseif (is_array($orders) && !empty($orders) && (isset($orders[0]) || array_is_list($orders))) {
                    $orderList = $orders;
                } else {
                    $orderList = [];
                }

                foreach ($orderList as $orderData) {
                    $ghnOrderCode = $orderData['order_code'] ?? null;
                    $clientOrderCode = $orderData['client_order_code'] ?? null;
                    if (!$ghnOrderCode) {
                        continue;
                    }

                    $order = $this->orderRepository->query()
                        ->where(function ($q) use ($ghnOrderCode, $clientOrderCode) {
                            $q->where('ghn_order_code', $ghnOrderCode);
                            if ($clientOrderCode) {
                                $q->orWhere('code', $clientOrderCode);
                            }
                        })
                        ->first();

                    if ($order && empty($order->ghn_order_code)) {
                        $order->update(['ghn_order_code' => $ghnOrderCode]);
                    }

                    $reconciliation = $this->reconciliationRepository->query()
                        ->where('organization_id', $organizationId)
                        ->where('ghn_order_code', $ghnOrderCode)
                        ->first();

                    try {
                        $orderDetail = $this->ghnService->getOrderDetail($ghnOrderCode);

                        $socData = null;
                        foreach (($orders['soc'] ?? []) as $s) {
                            if (($s['order_code'] ?? '') === $ghnOrderCode) {
                                $socData = $s;
                                break;
                            }
                        }

                        $fee = $orderDetail['fee'] ?? $socData['detail'] ?? [];
                        $codAmount = $orderDetail['cod_amount'] ?? $orderData['cod_amount'] ?? 0;

                        // GTB (Giao thất bại thu tiền) nằm ở cod_failed_amount trong API
                        $gtbAmount = $orderDetail['cod_failed_amount'] ?? $orderData['cod_failed_amount'] ?? 0;

                        // $codAmount += $gtbAmount; 

                        $mainService = $fee['main_service'] ?? $orderData['total_fee'] ?? 0;
                        $codFailedFee = $fee['cod_failed_fee'] ?? 0;

                        $shippingFee = $mainService + $codFailedFee;
                        $storageFee = $fee['station_do'] ?? 0;

                        if ($shippingFee == 0 && isset($socData['payment'][0]['value'])) {
                            $shippingFee = (float) $socData['payment'][0]['value'];
                        }

                        $totalFee = $codAmount + $shippingFee;

                        $reconciliationData = [
                            'organization_id' => $organizationId,
                            'reconciliation_date' => $this->normalizeDate($orderDetail['created_date'] ?? $orderData['created_date'] ?? now()->toDateString()),
                            'order_id' => $order?->id,
                            'ghn_order_code' => $ghnOrderCode,
                            'cod_amount' => $codAmount,
                            'shipping_fee' => $shippingFee,
                            'storage_fee' => $storageFee,
                            'total_fee' => $totalFee,
                            'ghn_to_name' => $orderDetail['to_name'] ?? $orderData['to_name'] ?? null,
                            'ghn_to_phone' => $orderDetail['to_phone'] ?? $orderData['to_phone'] ?? null,
                            'ghn_to_address' => $orderDetail['to_address'] ?? $orderData['to_address'] ?? null,
                            'ghn_status_label' => GhnOrderStatus::getLabel($orderDetail['status'] ?? $orderData['status'] ?? ''),
                            'ghn_created_at' => isset($orderDetail['created_date']) ? Carbon::parse($orderDetail['created_date']) : (isset($orderData['created_date']) ? Carbon::parse($orderData['created_date']) : null),
                            'ghn_updated_at' => isset($orderDetail['updated_date']) ? Carbon::parse($orderDetail['updated_date']) : null,
                            'ghn_items' => $orderDetail['items'] ?? null,
                            'ghn_payment_type_id' => $orderDetail['payment_type_id'] ?? null,
                            'ghn_weight' => $orderDetail['weight'] ?? null,
                            'ghn_content' => $orderDetail['content'] ?? null,
                            'ghn_required_note' => $orderDetail['required_note'] ?? null,
                            'ghn_employee_note' => $orderDetail['employee_note'] ?? $orderData['employee_note'] ?? null,
                            'ghn_cod_failed_amount' => $gtbAmount,
                        ];

                        $reconciliationData = $this->attachExchangeRateData($organizationId, $reconciliationData, $isForeignOrganization);

                        if ($reconciliation) {
                            if (
                                $reconciliation->status !== ReconciliationStatus::CONFIRMED->value
                                && $reconciliation->status !== ReconciliationStatus::CANCELLED->value
                            ) {
                                $reconciliationData['status'] = $reconciliation->status ?? ReconciliationStatus::PENDING->value;
                            }

                            $reconciliation->update($reconciliationData);
                            $updated++;
                        } else {
                            $reconciliationData['status'] = ReconciliationStatus::PENDING->value;
                            $reconciliationData['created_by'] = Auth::id();
                            $this->reconciliationRepository->create($reconciliationData);
                            $created++;
                        }
                    } catch (Throwable $e) {
                        Logging::error('Failed to get order detail for reconciliation', [
                            'ghn_order_code' => $ghnOrderCode,
                        ], $e);

                        $fallbackData = [
                            'organization_id' => $organizationId,
                            'reconciliation_date' => $this->normalizeDate($orderData['created_date'] ?? now()->toDateString()),
                            'order_id' => $order?->id,
                            'ghn_order_code' => $ghnOrderCode,
                            'cod_amount' => $orderData['cod_amount'] ?? 0,
                            'shipping_fee' => $orderData['total_fee'] ?? 0,
                            'storage_fee' => 0,
                            'total_fee' => $orderData['total_fee'] ?? 0,
                            'status' => ReconciliationStatus::PENDING->value,
                            'created_by' => Auth::id(),
                            'ghn_to_name' => $orderData['to_name'] ?? null,
                            'ghn_to_phone' => $orderData['to_phone'] ?? null,
                            'ghn_to_address' => $orderData['to_address'] ?? null,
                            'ghn_status_label' => GhnOrderStatus::getLabel($orderData['status'] ?? ''),
                            'ghn_created_at' => isset($orderData['created_date']) ? Carbon::parse($orderData['created_date']) : null,
                            'ghn_items' => $orderData['items'] ?? null,
                            'ghn_content' => $orderData['content'] ?? null,
                        ];

                        $fallbackData = $this->attachExchangeRateData($organizationId, $fallbackData, $isForeignOrganization);

                        if ($reconciliation) {
                            if (
                                $reconciliation->status !== ReconciliationStatus::CONFIRMED->value
                                && $reconciliation->status !== ReconciliationStatus::CANCELLED->value
                            ) {
                                $reconciliation->update($fallbackData);
                                $updated++;
                            }
                        } else {
                            $this->reconciliationRepository->create($fallbackData);
                            $created++;
                        }
                    }
                }

                $offset += $limit;
                $total = $orders['total'] ?? count($orderList);
                $hasMore = count($orderList) >= $limit && ($offset < $total);
            } while ($hasMore);

            Logging::web('GHN reconciliation sync completed', [
                'organization_id' => $organizationId,
                'created' => $created,
                'updated' => $updated,
            ]);

            return ServiceReturn::success(
                data: ['created' => $created, 'updated' => $updated],
                message: __('accounting.reconciliation.synced', ['count' => $created + $updated])
            );
        } catch (Throwable $e) {
            Logging::error('Sync reconciliation from GHN error', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ], $e);
            return ServiceReturn::error(__('accounting.reconciliation.sync_failed'));
        }
    }

    public function getOrderDetailFromGHN(int $reconciliationId): ServiceReturn
    {
        try {
            $reconciliation = $this->reconciliationRepository->find($reconciliationId);

            if (!$reconciliation) {
                return ServiceReturn::error(__('accounting.reconciliation.not_found'));
            }

            if (empty($reconciliation->ghn_order_code)) {
                return ServiceReturn::error(__('accounting.reconciliation.no_ghn_order_code'));
            }

            $config = $this->shippingConfigRepository->query()
                ->where('organization_id', $reconciliation->organization_id)
                ->first();

            if (!$config || empty($config->api_token) || empty($config->default_store_id)) {
                return ServiceReturn::error(__('accounting.reconciliation.config_not_found'));
            }

            $this->ghnService->setToken($config->api_token)->setShopId($config->default_store_id);

            $orderDetail = $this->ghnService->getOrderDetail($reconciliation->ghn_order_code);

            return ServiceReturn::success(
                data: $orderDetail,
                message: __('accounting.reconciliation.detail_loaded')
            );
        } catch (Throwable $e) {
            Logging::error('Get order detail from GHN error', [
                'reconciliation_id' => $reconciliationId,
                'error' => $e->getMessage(),
            ], $e);
            return ServiceReturn::error(__('accounting.reconciliation.detail_load_failed') . ': ' . $e->getMessage(), $e);
        }
    }

    public function syncOrderDetailFromGHN(int $reconciliationId): ServiceReturn
    {
        try {
            $reconciliation = $this->reconciliationRepository->find($reconciliationId);

            if (!$reconciliation) {
                return ServiceReturn::error(__('accounting.reconciliation.not_found'));
            }

            if (empty($reconciliation->ghn_order_code)) {
                return ServiceReturn::error(__('accounting.reconciliation.no_ghn_order_code'));
            }

            $config = $this->shippingConfigRepository->query()
                ->where('organization_id', $reconciliation->organization_id)
                ->first();

            if (!$config || empty($config->api_token) || empty($config->default_store_id)) {
                return ServiceReturn::error(__('accounting.reconciliation.config_not_found'));
            }

            $this->ghnService->setToken($config->api_token)->setShopId($config->default_store_id);
            $orderDetail = $this->ghnService->getOrderDetail($reconciliation->ghn_order_code);

            // Tìm thêm thông tin phí từ search nếu detail không có
            $fee = $orderDetail['fee'] ?? [];
            if (empty($fee)) {
                $searchResult = $this->ghnService->searchOrders(['order_code' => $reconciliation->ghn_order_code]);
                $socData = null;
                foreach (($searchResult['soc'] ?? []) as $s) {
                    if (($s['order_code'] ?? '') === $reconciliation->ghn_order_code) {
                        $socData = $s;
                        break;
                    }
                }
                $fee = $socData['detail'] ?? [];
            }

            $codAmount = $orderDetail['cod_amount'] ?? $reconciliation->cod_amount;

            $mainService = $fee['main_service'] ?? $reconciliation->shipping_fee;
            $codFailedFee = $fee['cod_failed_fee'] ?? 0;

            $shippingFee = $mainService + $codFailedFee;
            $storageFee = $fee['station_do'] ?? $reconciliation->storage_fee;

            if ($shippingFee == 0 && isset($socData['payment'][0]['value'])) {
                $shippingFee = (float) $socData['payment'][0]['value'];
            }

            $totalFee = $codAmount + $shippingFee;

            $updateData = [
                'cod_amount' => $codAmount,
                'shipping_fee' => $shippingFee,
                'storage_fee' => $storageFee,
                'total_fee' => $totalFee,
                'ghn_cod_failed_amount' => $orderDetail['cod_failed_amount'] ?? $reconciliation->ghn_cod_failed_amount,
                'ghn_employee_note' => $orderDetail['employee_note'] ?? $reconciliation->ghn_employee_note,
                'reconciliation_date' => $this->normalizeDate($orderDetail['created_date'] ?? $reconciliation->reconciliation_date),
            ];

            $updateData = $this->attachExchangeRateData(
                (int) $reconciliation->organization_id,
                $updateData,
                $this->isForeignOrganization((int) $reconciliation->organization_id)
            );

            $reconciliation->update($updateData);

            return ServiceReturn::success(
                data: $reconciliation->fresh(),
                message: __('accounting.reconciliation.detail_synced')
            );
        } catch (Throwable $e) {
            Logging::error('Sync order detail from GHN error', [
                'reconciliation_id' => $reconciliationId,
                'error' => $e->getMessage(),
            ], $e);
            return ServiceReturn::error(__('accounting.reconciliation.detail_sync_failed') . ': ' . $e->getMessage(), $e);
        }
    }

    public function updateOrderOnGHN(int $reconciliationId, array $updateData): ServiceReturn
    {
        try {
            $reconciliation = $this->reconciliationRepository->find($reconciliationId);

            if (!$reconciliation) {
                return ServiceReturn::error(__('accounting.reconciliation.not_found'));
            }

            if (empty($reconciliation->ghn_order_code)) {
                return ServiceReturn::error(__('accounting.reconciliation.no_ghn_order_code'));
            }

            $config = $this->shippingConfigRepository->query()
                ->where('organization_id', $reconciliation->organization_id)
                ->first();

            if (!$config || empty($config->api_token) || empty($config->default_store_id)) {
                return ServiceReturn::error(__('accounting.reconciliation.config_not_found'));
            }

            $this->ghnService->setToken($config->api_token)->setShopId($config->default_store_id);

            $updatedFields = [];

            if (array_key_exists('cod_amount', $updateData)) {
                $this->ghnService->updateCOD($reconciliation->ghn_order_code, $updateData['cod_amount']);
                $updatedFields['cod_amount'] = $updateData['cod_amount'];
            }

            $orderUpdateFields = [
                'to_name',
                'to_phone',
                'to_address',
                'to_ward_code',
                'to_district_id',
                'payment_type_id',
                'weight',
                'length',
                'width',
                'height',
                'note',
                'content',
                'required_note',
            ];

            $orderFieldsToUpdate = array_intersect_key($updateData, array_flip($orderUpdateFields));

            if (!empty($orderFieldsToUpdate)) {
                $this->ghnService->updateOrder($reconciliation->ghn_order_code, $orderFieldsToUpdate);
                $updatedFields = array_merge($updatedFields, $orderFieldsToUpdate);
            }

            $orderDetail = $this->ghnService->getOrderDetail($reconciliation->ghn_order_code);

            $fee = $orderDetail['fee'] ?? [];
            $codAmount = $orderDetail['cod_amount'] ?? $reconciliation->cod_amount;
            $shippingFee = $fee['main_service'] ?? $reconciliation->shipping_fee;
            $storageFee = $fee['station_do'] ?? $reconciliation->storage_fee;
            $totalFee = ($fee['main_service'] ?? 0) + ($fee['cod_fee'] ?? 0) + ($fee['station_do'] ?? 0) + ($fee['insurance'] ?? 0);

            $reconciliationUpdateData = [
                'cod_amount' => $codAmount,
                'shipping_fee' => $shippingFee,
                'storage_fee' => $storageFee,
                'total_fee' => $totalFee,
                'reconciliation_date' => $this->normalizeDate($orderDetail['created_date'] ?? $reconciliation->reconciliation_date),
            ];

            $reconciliationUpdateData = $this->attachExchangeRateData(
                (int) $reconciliation->organization_id,
                $reconciliationUpdateData,
                $this->isForeignOrganization((int) $reconciliation->organization_id)
            );

            $reconciliation->update($reconciliationUpdateData);

            return ServiceReturn::success(
                data: ['reconciliation' => $reconciliation->fresh(), 'updated_fields' => $updatedFields],
                message: __('accounting.reconciliation.order_updated')
            );
        } catch (Throwable $e) {
            Logging::error('Update order on GHN error', [
                'reconciliation_id' => $reconciliationId,
                'update_data' => $updateData,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], $e);
            return ServiceReturn::error(__('accounting.reconciliation.order_update_failed'));
        }
    }

    public function confirmReconciliation(int $reconciliationId): ServiceReturn
    {
        try {
            $reconciliation = $this->reconciliationRepository->find($reconciliationId);

            if (!$reconciliation) {
                return ServiceReturn::error(__('accounting.reconciliation.not_found'));
            }

            $reconciliation->update([
                'status' => ReconciliationStatus::CONFIRMED->value,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            return ServiceReturn::success(data: $reconciliation, message: __('accounting.reconciliation.confirmed'));
        } catch (Throwable $e) {
            Logging::error('Confirm reconciliation error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error(__('accounting.reconciliation.confirm_failed'));
        }
    }

    /**
     * Backfill exchange_rate_id + converted_amount cho các bản ghi đối soát trong khoảng ngày.
     */
    public function applyExchangeRateForDateRange(int $organizationId, string $fromDate, string $toDate): int
    {
        if (!$this->isForeignOrganization($organizationId)) {
            return 0;
        }

        $from = Carbon::parse($fromDate)->toDateString();
        $to = Carbon::parse($toDate)->toDateString();
        $updatedCount = 0;

        $this->reconciliationRepository->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('reconciliation_date', [$from, $to])
            ->orderBy('id')
            ->chunkById(200, function ($reconciliations) use (&$updatedCount, $organizationId) {
                foreach ($reconciliations as $reconciliation) {
                    $payload = [
                        'reconciliation_date' => $this->normalizeDate((string) $reconciliation->reconciliation_date),
                        'cod_amount' => (float) $reconciliation->cod_amount,
                    ];

                    $payload = $this->attachExchangeRateData($organizationId, $payload, true);

                    $changes = [];

                    if (
                        array_key_exists('exchange_rate_id', $payload)
                        && (int) $reconciliation->exchange_rate_id !== (int) ($payload['exchange_rate_id'] ?? 0)
                    ) {
                        $changes['exchange_rate_id'] = $payload['exchange_rate_id'];
                    }

                    if (
                        array_key_exists('converted_amount', $payload)
                        && (float) $reconciliation->converted_amount !== (float) ($payload['converted_amount'] ?? 0)
                    ) {
                        $changes['converted_amount'] = $payload['converted_amount'];
                    }

                    if (!empty($changes)) {
                        $reconciliation->update($changes);
                        $updatedCount++;
                    }
                }
            });

        return $updatedCount;
    }

    private function attachExchangeRateData(int $organizationId, array $reconciliationData, bool $isForeignOrganization): array
    {
        if (!$isForeignOrganization) {
            $reconciliationData['exchange_rate_id'] = null;
            $reconciliationData['converted_amount'] = null;
            return $reconciliationData;
        }

        $rateDate = $this->normalizeDate($reconciliationData['reconciliation_date'] ?? now()->toDateString());

        $exchangeRate = $this->exchangeRateRepository->query()
            ->where('organization_id', $organizationId)
            ->whereDate('rate_date', $rateDate)
            ->where('from_currency', 'USD')
            ->where('to_currency', 'VND')
            ->first();

        if (!$exchangeRate) {
            $latestRate = $this->fetchLatestUsdToVndRate();

            if ($latestRate !== null) {
                $exchangeRate = $this->exchangeRateRepository->query()->updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'rate_date' => $rateDate,
                        'from_currency' => 'USD',
                        'to_currency' => 'VND',
                    ],
                    [
                        'rate' => $latestRate,
                        'source' => 'api',
                        'note' => 'Auto synced when reconciliation is created/updated',
                    ]
                );
            }
        }

        if ($exchangeRate) {
            $codAmount = (float) ($reconciliationData['cod_amount'] ?? 0);
            $rate = (float) $exchangeRate->rate;
            $reconciliationData['exchange_rate_id'] = $exchangeRate->id;
            $reconciliationData['converted_amount'] = $rate > 0 ? round($codAmount / $rate, 2) : null;
        }

        return $reconciliationData;
    }

    private function fetchLatestUsdToVndRate(): ?float
    {
        $apiKey = config('services.exchangerate.api_key', env('V6_API_KEY'));

        if (empty($apiKey)) {
            Logging::error('ExchangeRate API key is missing when attaching reconciliation exchange rate');
            return null;
        }

        $cacheKey = 'exchange_rate_api.latest.usd_vnd.reconciliation';

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($apiKey) {
            try {
                $response = Http::retry(3, 500)
                    ->timeout(10)
                    ->acceptJson()
                    ->get('https://v6.exchangerate-api.com/v6/' . $apiKey . '/latest/USD');

                if (!$response->successful()) {
                    Logging::error('ExchangeRate API failed for reconciliation', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                $data = $response->json();
                $rate = $data['conversion_rates']['VND'] ?? null;

                if (!is_numeric($rate) || (float) $rate <= 0) {
                    Logging::error('Invalid ExchangeRate API payload for reconciliation', [
                        'payload' => $data,
                    ]);
                    return null;
                }

                return (float) $rate;
            } catch (Throwable $e) {
                Logging::error('ExchangeRate API exception in reconciliation', [
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    private function normalizeDate(?string $date): string
    {
        try {
            return Carbon::parse($date ?? now())->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }

    private function isForeignOrganization(int $organizationId): bool
    {
        return (bool) Organization::query()
            ->where('id', $organizationId)
            ->value('is_foreign');
    }
}

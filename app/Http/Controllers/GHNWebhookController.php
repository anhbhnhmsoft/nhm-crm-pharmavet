<?php

namespace App\Http\Controllers;

use App\Services\Warehouse\ShippingStatusSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GHNWebhookController extends Controller
{
    public function __construct(
        protected ShippingStatusSyncService $shippingStatusSyncService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        if (!config('warehouse.features.shipping_sync_v1', true)) {
            return response()->json(['success' => true]);
        }

        $result = $this->shippingStatusSyncService->handleWebhook($request->all());

        if ($result->isError()) {
            return response()->json([
                'success' => false,
                'message' => $result->getMessage(),
            ], 400);
        }

        return response()->json(['success' => true]);
    }
}

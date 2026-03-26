<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderShippingController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {
    }

    public function requestRedelivery(Request $request, Order $order): JsonResponse
    {
        $result = $this->orderService->requestRedelivery($order, $request->all());

        if ($result->isError()) {
            return response()->json([
                'success' => false,
                'message' => $result->getMessage(),
            ], 400);
        }

        return response()->json(['success' => true]);
    }
}

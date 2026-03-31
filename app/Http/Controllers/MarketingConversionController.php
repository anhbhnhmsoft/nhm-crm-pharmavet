<?php

namespace App\Http\Controllers;

use App\Services\Marketing\MarketingConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MarketingConversionController extends Controller
{
    public function __construct(
        protected MarketingConversionService $marketingConversionService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        if (!config('marketing.features.integration_v2', false)) {
            return response()->json(['message' => 'Feature disabled'], 404);
        }

        $expectedSecret = (string) config('marketing.facebook.capi_secret', '');
        if ($expectedSecret !== '') {
            $signature = (string) $request->header('X-Marketing-Signature', '');
            if ($signature !== $expectedSecret) {
                return response()->json([
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'integration_id' => ['required', 'integer', 'exists:integrations,id'],
            'event_name' => ['required', 'in:AddToCart,Lead,Purchase'],
            'event_id' => ['nullable', 'string', 'max:120'],
            'event_time' => ['nullable', 'integer'],
            'event_source_url' => ['nullable', 'url'],
            'action_source' => ['nullable', 'string', 'max:50'],
            'user_data' => ['required', 'array'],
            'custom_data' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid payload',
                'errors' => $validator->errors()->toArray(),
            ], 400);
        }

        $log = $this->marketingConversionService->queueEvent($validator->validated());

        return response()->json([
            'message' => 'Queued',
            'event_log_id' => $log->id,
        ], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Marketing\WebsiteLeadIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteV2LeadController extends Controller
{
    public function __construct(
        protected WebsiteLeadIngestService $websiteLeadIngestService,
    ) {
    }

    public function ingest(Request $request, string $siteId): JsonResponse
    {
        if (!config('marketing.features.integration_v2', false)) {
            return response()->json(['message' => 'Feature disabled'], 404);
        }

        $tokenHeader = config('marketing.website_v2.auth_header', 'X-Website-Token');
        $token = (string) ($request->header($tokenHeader) ?? $request->bearerToken() ?? '');
        $result = $this->websiteLeadIngestService->ingest($siteId, (array) $request->all(), $token);

        if ($result->isError()) {
            $status = $result->getMessage() === 'Unauthorized' ? 403 : 400;
            return response()->json([
                'message' => $result->getMessage(),
                'errors' => data_get($result->getData(), 'errors'),
            ], $status);
        }

        return response()->json([
            'message' => 'Accepted',
            'data' => $result->getData(),
        ], 200);
    }

    public function ping(Request $request, string $siteId): JsonResponse
    {
        if (!config('marketing.features.integration_v2', false)) {
            return response()->json(['message' => 'Feature disabled'], 404);
        }

        $tokenHeader = config('marketing.website_v2.auth_header', 'X-Website-Token');
        $token = (string) ($request->header($tokenHeader) ?? $request->bearerToken() ?? '');
        $result = $this->websiteLeadIngestService->ping($siteId, (array) $request->all(), $token);

        if ($result->isError()) {
            $status = $result->getMessage() === 'Unauthorized' ? 403 : 400;
            return response()->json([
                'message' => $result->getMessage(),
                'errors' => data_get($result->getData(), 'errors'),
            ], $status);
        }

        return response()->json([
            'message' => 'OK',
            'data' => $result->getData(),
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facebook\ConnectFacebookRequest;
use App\Repositories\IntegrationRepository;
use App\Services\Integrations\MetaBusinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FacebookConnectionController extends Controller
{
    public function __construct(
        protected MetaBusinessService $metaBusinessService,
        protected IntegrationRepository $integrationRepository,
    ) {
    }

    public function connect(ConnectFacebookRequest $request): JsonResponse
    {
        $user = Auth::user() ?? Auth::guard('api')->user();
        $result = $this->metaBusinessService->connectWithUserAccessToken($user, (string) $request->string('userAccessToken'));

        if ($result->isError()) {
            return response()->json([
                'message' => $result->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ]);
    }

    public function myPages(): JsonResponse
    {
        $user = Auth::user() ?? Auth::guard('api')->user();
        $integration = $this->integrationRepository->findLatestFacebookByUser(
            (int) $user->organization_id,
            (int) $user->id
        );

        if (!$integration) {
            return response()->json([
                'message' => __('common.success.get_success'),
                'data' => [],
            ]);
        }

        $pages = $integration->facebookPages()
            ->orderByDesc('id')
            ->get()
            ->map(fn ($entity) => [
                'id' => $entity->id,
                'page_id' => $entity->external_id,
                'page_name' => $entity->name,
                'status' => $entity->status,
                'approved_at' => $entity->approved_at?->toDateTimeString(),
                'rejected_at' => $entity->rejected_at?->toDateTimeString(),
                'webhook_subscribed_at' => $entity->webhook_subscribed_at?->toDateTimeString(),
                'last_lead_received_at' => $entity->last_lead_received_at?->toDateTimeString(),
                'status_reason' => $entity->status_reason,
                'metadata' => $entity->metadata ?? [],
            ])
            ->values();

        return response()->json([
            'message' => __('common.success.get_success'),
            'data' => [
                'integration_id' => $integration->id,
                'pages' => $pages,
            ],
        ]);
    }
}

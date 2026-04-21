<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Common\Constants\User\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facebook\AdminApproveFacebookRequest;
use App\Http\Requests\Facebook\AdminRejectFacebookRequest;
use App\Models\Integration;
use App\Repositories\IntegrationRepository;
use App\Services\Integrations\MetaBusinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FacebookConnectionApprovalController extends Controller
{
    public function __construct(
        protected MetaBusinessService $metaBusinessService,
        protected IntegrationRepository $integrationRepository,
    ) {
    }

    public function approve(AdminApproveFacebookRequest $request): JsonResponse
    {
        $user = Auth::user() ?? Auth::guard('api')->user();
        $integration = $this->resolveIntegration($user, (int) $request->integer('integration_id'));

        if (!$integration) {
            return response()->json(['message' => __('messages.integration.error.not_found')], 404);
        }

        $result = $this->metaBusinessService->approveConnections(
            $user,
            $integration,
            array_values((array) $request->input('page_ids', []))
        );

        if ($result->isError()) {
            return response()->json(['message' => $result->getMessage()], 422);
        }

        return response()->json([
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ]);
    }

    public function reject(AdminRejectFacebookRequest $request): JsonResponse
    {
        $user = Auth::user() ?? Auth::guard('api')->user();
        $integration = $this->resolveIntegration($user, (int) $request->integer('integration_id'));

        if (!$integration) {
            return response()->json(['message' => __('messages.integration.error.not_found')], 404);
        }

        $result = $this->metaBusinessService->rejectConnections(
            $user,
            $integration,
            array_values((array) $request->input('page_ids', [])),
            $request->string('reason')->toString() ?: null
        );

        if ($result->isError()) {
            return response()->json(['message' => $result->getMessage()], 422);
        }

        return response()->json([
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ]);
    }

    protected function resolveIntegration($user, int $integrationId): ?Integration
    {
        if ((int) $user->role === UserRole::SUPER_ADMIN->value) {
            $integration = $this->integrationRepository->find($integrationId);

            return $integration instanceof Integration ? $integration : null;
        }

        return $this->integrationRepository->findByIdAndOrganization($integrationId, (int) $user->organization_id);
    }
}

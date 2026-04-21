<?php

namespace App\Http\Controllers;

use App\Common\Constants\Marketing\IntegrationEntityType;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\StatusConnect;
use App\Filament\Clusters\Marketing\Resources\Integrations\IntegrationResource;
use App\Models\Integration;
use App\Repositories\IntegrationRepository;
use App\Services\Integrations\IntegrationService;
use App\Services\Integrations\MetaBusinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FacebookAuthController extends Controller
{
    protected const OAUTH_CONTEXT_KEY = 'facebook_oauth_context';

    public function __construct(
        protected MetaBusinessService $metaService,
        protected IntegrationService $integrationService,
        protected IntegrationRepository $integrationRepository,
    ) {
    }

    public function redirect(Request $request, string $integration): RedirectResponse|View|Response
    {
        $user = Auth::user();
        $isPopup = $this->isPopupRequest($request);

        if (!in_array($integration, ['temp', 'new'], true)) {
            if (!ctype_digit($integration)) {
                return $this->handleError(__('filament.integration.errors.integration_not_found'), $isPopup);
            }

            $record = $this->integrationRepository->findByIdAndOrganization((int) $integration, (int) $user->organization_id);
            if (!$record || !$this->isFacebookIntegration($record)) {
                return $this->handleError(__('common.error.403'), $isPopup, statusCode: 403);
            }

            $integration = (string) $record->id;
        }

        $state = Str::random(48);
        session([
            self::OAUTH_CONTEXT_KEY => [
                'integration_id' => $integration,
                'organization_id' => (int) $user->organization_id,
                'user_id' => (int) $user->id,
                'is_popup' => $isPopup,
                'state' => $state,
                'issued_at' => now()->timestamp,
            ],
        ]);

        return redirect($this->metaService->getRedirectUrl($state));
    }

    public function callback(Request $request): RedirectResponse|View|Response
    {
        $context = $this->getOAuthContext();
        $isPopup = (bool) ($context['is_popup'] ?? false);

        if ($context === []) {
            return $this->handleError(__('filament.integration.errors.no_integration_found'), $isPopup);
        }

        if ($request->filled('error')) {
            session()->forget([self::OAUTH_CONTEXT_KEY, 'integration_id', 'is_popup']);
            $message = (string) $request->query('error_message', __('filament.integration.errors.connection_failed'));
            return $this->handleError($message, $isPopup);
        }

        $expectedState = (string) ($context['state'] ?? '');
        $actualState = (string) $request->query('state', '');
        if ($expectedState === '' || $actualState === '' || !hash_equals($expectedState, $actualState)) {
            session()->forget([self::OAUTH_CONTEXT_KEY, 'integration_id', 'is_popup']);
            return $this->handleError(__('filament.integration.errors.invalid_oauth_state'), $isPopup);
        }

        $currentUser = Auth::user();
        if (
            (int) ($context['organization_id'] ?? 0) !== (int) $currentUser->organization_id
            || (int) ($context['user_id'] ?? 0) !== (int) $currentUser->id
        ) {
            session()->forget([self::OAUTH_CONTEXT_KEY, 'integration_id', 'is_popup']);
            return $this->handleError(__('common.error.403'), $isPopup, statusCode: 403);
        }

        $integrationId = (string) ($context['integration_id'] ?? '');
        if (in_array($integrationId, ['temp', 'new'], true)) {
            return $this->handleTempIntegration($isPopup);
        }

        if (!ctype_digit($integrationId)) {
            session()->forget([self::OAUTH_CONTEXT_KEY, 'integration_id', 'is_popup']);
            return $this->handleError(__('filament.integration.errors.integration_not_found'), $isPopup);
        }

        $integration = $this->integrationRepository
            ->findByIdAndOrganization((int) $integrationId, (int) $currentUser->organization_id);

        if (!$integration || !$this->isFacebookIntegration($integration)) {
            session()->forget([self::OAUTH_CONTEXT_KEY, 'integration_id', 'is_popup']);
            return $this->handleError(__('common.error.403'), $isPopup, statusCode: 403);
        }

        $result = $this->metaService->handleCallback($integration);
        session()->forget([self::OAUTH_CONTEXT_KEY, 'integration_id', 'is_popup']);

        if ($result->isError()) {
            return $this->handleError($result->getMessage(), $isPopup, $integration);
        }

        $integration->refresh();
        $pagesCount = $integration->entities()
            ->where('type', IntegrationEntityType::PAGE_META->value)
            ->count();

        if ($isPopup) {
            return view('filament.oauth.callback-success', [
                'integrationId' => $integration->id,
                'pagesCount' => $pagesCount,
                'lastSync' => $integration->last_sync_at ? $integration->last_sync_at->diffForHumans() : (string) now(),
                'redirectUrl' => IntegrationResource::getUrl('edit', ['record' => $integration]),
            ]);
        }

        return redirect()
            ->to(IntegrationResource::getUrl('edit', ['record' => $integration]))
            ->with('success', __('messages.meta_business.success.pending_approval', ['count' => $pagesCount]));
    }

    protected function handleTempIntegration(bool $isPopup): RedirectResponse|View|Response
    {
        $result = $this->integrationService->initIntegration([
            'organization_id' => Auth::user()->organization_id,
            'type' => IntegrationType::FACEBOOK_ADS->value,
            'name' => __('filament.integration.defaults.facebook_name'),
            'status' => StatusConnect::PENDING->value,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        if (!$result->isSuccess()) {
            session()->forget([self::OAUTH_CONTEXT_KEY, 'integration_id', 'is_popup']);
            return $this->handleError($result->getMessage(), $isPopup);
        }

        $integration = $result->getData();
        $callbackResult = $this->metaService->handleCallback($integration);
        session()->forget([self::OAUTH_CONTEXT_KEY, 'integration_id', 'is_popup']);

        if ($callbackResult->isError()) {
            $integration->delete();
            return $this->handleError($callbackResult->getMessage(), $isPopup);
        }

        $integration->refresh();
        $pagesCount = $integration->entities()
            ->where('type', IntegrationEntityType::PAGE_META->value)
            ->count();

        if ($isPopup) {
            return view('filament.oauth.callback-success', [
                'integrationId' => $integration->id,
                'pagesCount' => $pagesCount,
                'lastSync' => $integration->last_sync_at ? $integration->last_sync_at->diffForHumans() : (string) now(),
                'redirectUrl' => IntegrationResource::getUrl('edit', ['record' => $integration]),
            ]);
        }

        return redirect(IntegrationResource::getUrl('edit', ['record' => $integration]))
            ->with('success', __('messages.meta_business.success.pending_approval', ['count' => $pagesCount]));
    }

    protected function handleError(
        string $message,
        bool $isPopup,
        ?Integration $integration = null,
        int $statusCode = 400
    ): RedirectResponse|View|Response {
        if ($isPopup) {
            return response()->view('filament.oauth.callback-error', [
                'error' => $message,
            ], $statusCode);
        }

        $route = $integration
            ? IntegrationResource::getUrl('edit', ['record' => $integration])
            : IntegrationResource::getUrl('index');

        return redirect($route)->with('error', $message);
    }

    public function syncPages(Request $request, Integration $integration): JsonResponse
    {
        if (!$this->canMutateIntegration($integration)) {
            return response()->json([
                'success' => false,
                'message' => __('common.error.403'),
            ], 403);
        }

        $result = $this->metaService->syncPages($integration);

        if ($result->isSuccess()) {
            return response()->json([
                'success' => true,
                'message' => $result->getMessage(),
                'count' => data_get($result->getData(), 'count', 0),
                'subscribed_count' => data_get($result->getData(), 'subscribed_count', 0),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result->getMessage(),
        ], 422);
    }

    public function disconnect(Request $request, Integration $integration): JsonResponse
    {
        if (!$this->canMutateIntegration($integration)) {
            return response()->json([
                'success' => false,
                'message' => __('common.error.403'),
            ], 403);
        }

        $result = $this->metaService->disconnect($integration);

        if ($result->isSuccess()) {
            return response()->json([
                'success' => true,
                'message' => __('filament.integration.api.disconnected'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result->getMessage(),
        ], 422);
    }

    protected function canMutateIntegration(Integration $integration): bool
    {
        return (int) $integration->organization_id === (int) Auth::user()->organization_id
            && $this->isFacebookIntegration($integration);
    }

    protected function isPopupRequest(Request $request): bool
    {
        return $request->boolean('popup')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }

    protected function getOAuthContext(): array
    {
        $context = session(self::OAUTH_CONTEXT_KEY, []);
        if (is_array($context) && $context !== []) {
            return $context;
        }

        // Backward-compatible fallback for legacy popup sessions.
        $legacyIntegrationId = session('integration_id');
        if (!$legacyIntegrationId) {
            return [];
        }

        return [
            'integration_id' => (string) $legacyIntegrationId,
            'organization_id' => (int) Auth::user()->organization_id,
            'user_id' => (int) Auth::id(),
            'is_popup' => (bool) session('is_popup', false),
            'state' => (string) session('oauth_state', ''),
        ];
    }

    protected function isFacebookIntegration(Integration $integration): bool
    {
        if ((int) $integration->type === IntegrationType::FACEBOOK_ADS->value) {
            return true;
        }

        return (int) $integration->type === 0
            && !empty(data_get($integration->config, 'webhook_verify_token'));
    }
}

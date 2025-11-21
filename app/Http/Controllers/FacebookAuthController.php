<?php

namespace App\Http\Controllers;

use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\StatusConnect;
use App\Filament\Clusters\Marketing\Resources\Integrations\IntegrationResource;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Integrations\MetaBusinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Integrations\IntegrationService;

class FacebookAuthController extends Controller
{
    protected MetaBusinessService $metaService;
    protected IntegrationService $integrationService;

    /**
     * @param MetaBusinessService $metaService
     * @param IntegrationService $integrationService
     */
    public function __construct(MetaBusinessService $metaService, IntegrationService $integrationService)
    {
        $this->metaService = $metaService;
        $this->integrationService = $integrationService;
    }

    /**
     * Redirect to Facebook OAuth
     */
    public function redirect(Request $request, $integration)
    {
        // Store integration ID in session
        session([
            'integration_id' => $integration,
            'is_popup' => $request->has('popup') || $request->header('X-Requested-With') !== null,
        ]);

        return redirect($this->metaService->getRedirectUrl());
    }

    /**
     * Handle Facebook callback
     * @param Request $request
     */
    public function callback(Request $request)
    {

        $integrationId = session('integration_id');
        $isPopup = session('is_popup', false);

        // Handle temporary integration (create on callback)
        if ($integrationId === 'temp') {
            return $this->handleTempIntegration($request, $isPopup);
        }

        // Validate integration exists
        if (!$integrationId || $integrationId === 'new') {
            return $this->handleError(
                __('integration.errors.no_integration_found'),
                $isPopup
            );
        }

        $result = $this->integrationService->getIntegration($integrationId);

        if (!$result->isSuccess()) {
            return $this->handleError(
                __('integration.errors.integration_not_found'),
                $isPopup
            );
        }

        $integration = $result->getData();

        // Handle OAuth callback
        $success = $this->metaService->handleCallback($integration);

        session()->forget(['integration_id', 'is_popup']);

        if ($success) {
            $pagesCount = $integration->entities()->where('type', \App\Common\Constants\Marketing\IntegrationEntityType::PAGE_META->value)->count();

            if ($isPopup) {
                return view('filament.oauth.callback-success', [
                    'integrationId' => $integration->id,
                    'pagesCount' => $pagesCount,
                    'lastSync' => $integration->last_sync_at ? $integration->last_sync_at->diffForHumans() : (string) now(),
                    'redirectUrl' => \App\Filament\Clusters\Marketing\Resources\Integrations\IntegrationResource::getUrl('edit', ['record' => $integration]),
                ]);
            }

            // Normal redirect
            return redirect()
                ->to(\App\Filament\Clusters\Marketing\Resources\Integrations\IntegrationResource::getUrl('edit', ['record' => $integration]))
                ->with('success', __('integration.success.facebook_connected', ['count' => $pagesCount]));
        }

        return $this->handleError(
            __('integration.errors.connection_failed'),
            $isPopup,
            $integration
        );
    }

    /**
     * Handle temporary integration (user connected before saving)
     */
    protected function handleTempIntegration(Request $request, bool $isPopup)
    {
        // Create temporary integration record
        $result = $this->integrationService->initIntegration([
            'organization_id' => Auth::user()->organization_id,
            'type' => IntegrationType::FACEBOOK_ADS->value, // Facebook Ads
            'name' => __('integration.defaults.facebook_name'),
            'status' => StatusConnect::CONNECTED->value,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        if (!$result->isSuccess()) {
            return $this->handleError($result->getMessage(), $isPopup);
        }

        $integration = $result->getData();

        // Handle callback
        $success = $this->metaService->handleCallback($integration);

        session()->forget(['integration_id', 'is_popup']);

        if ($success) {
            $pagesCount = $integration->entities()->where('type', \App\Common\Constants\Marketing\IntegrationEntityType::PAGE_META->value)->count();

            if ($isPopup) {
                return view('filament.oauth.callback-success', [
                    'integrationId' => $integration->id,
                    'pagesCount' => $pagesCount,
                    'redirectUrl' => IntegrationResource::getUrl('edit', ['record' => $integration]),
                ]);
            }

            return redirect(IntegrationResource::getUrl('edit', ['record' => $integration]))
                ->with('success', __('integration.success.facebook_connected', ['count' => $pagesCount]));
        }

        // Failed - delete temp integration
        $integration->delete();

        return $this->handleError(
            __('integration.errors.connection_failed'),
            $isPopup
        );
    }

    /**
     * Handle error response
     */
    protected function handleError(string $message, bool $isPopup, ?Integration $integration = null)
    {
        if ($isPopup) {
            // Return error page for popup
            return view('filament.oauth.callback-error', [
                'error' => $message,
            ]);
        }

        // Normal redirect
        $route = $integration
            ? IntegrationResource::getUrl('edit', ['record' => $integration])
            : IntegrationResource::getUrl('index');

        return redirect($route)->with('error', $message);
    }


    /**
     * Sync pages from Facebook
     */
    public function syncPages(Request $request, Integration $integration)
    {
        /**
         * @var ServiceReturn $result
         */
        $result = $this->metaService->syncPages($integration);

        if ($result->isSuccess()) {
            return response()->json([
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'count' => $result->getData()['count'] ?? 0,
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => $result->getMessage(),
        ], 500);
    }

    /**
     * Disconnect integration
     */
    public function disconnect(Request $request, Integration $integration)
    {
        /**
         * @var ServiceReturn $result
         */
        $result = $this->metaService->disconnect($integration);

        if ($result->isSuccess()) {
            return response()->json([
                'success' => true,
                'message' => __('integration.api.disconnected'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result->getMessage(),
        ], 500);
    }
}

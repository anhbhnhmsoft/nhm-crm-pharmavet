<?php

namespace App\Http\Controllers;

use App\Http\Requests\Accounting\SyncReconciliationRequest;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ReconciliationController extends Controller
{
    public function __construct(
        protected ReconciliationService $reconciliationService
    ) {}

    /**
     * Đồng bộ đối soát từ GHN
     */
    public function syncFromGHN(SyncReconciliationRequest $request): JsonResponse|RedirectResponse
    {
        $organizationId = Auth::user()->organization_id;
        $result = $this->reconciliationService->syncReconciliationFromGHN(
            $organizationId,
            $request->validated()['from_date'],
            $request->validated()['to_date']
        );

        if ($result->isError()) {
            return back()->withErrors(['error' => $result->getMessage()]);
        }

        return back()->with('success', $result->getMessage());
    }

    /**
     * Xác nhận đối soát
     */
    public function confirm(int $reconciliationId): JsonResponse|RedirectResponse
    {
        $result = $this->reconciliationService->confirmReconciliation($reconciliationId);

        if ($result->isError()) {
            return back()->withErrors(['error' => $result->getMessage()]);
        }

        return back()->with('success', $result->getMessage());
    }
}

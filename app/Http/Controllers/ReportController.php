<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Báo cáo kinh doanh
     */
    public function getBusinessReport(): JsonResponse
    {
        $organizationId = Auth::user()->organization_id;
        $fromDate = request()->input('from_date', now()->startOfMonth()->toDateString());
        $toDate = request()->input('to_date', now()->endOfMonth()->toDateString());
        $type = request()->input('type', 'day');

        $result = $this->reportService->getBusinessReport($organizationId, $fromDate, $toDate, $type);

        if ($result->isError()) {
            return response()->json(['error' => $result->getMessage()], 400);
        }

        return response()->json($result->getData());
    }
}

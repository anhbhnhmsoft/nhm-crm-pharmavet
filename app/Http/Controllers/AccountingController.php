<?php

namespace App\Http\Controllers;

use App\Http\Requests\Accounting\StoreExchangeRateRequest;
use App\Http\Requests\Accounting\StoreExpenseRequest;
use App\Http\Requests\Accounting\StoreRevenueRequest;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AccountingController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    /**
     * Tạo tỉ giá mới
     */
    public function storeExchangeRate(StoreExchangeRateRequest $request): JsonResponse|RedirectResponse
    {
        $result = $this->accountingService->createExchangeRate($request->validated());

        if ($result->isError()) {
            return back()->withErrors(['error' => $result->getMessage()]);
        }

        return back()->with('success', $result->getMessage());
    }

    /**
     * Tạo chi phí mới
     */
    public function storeExpense(StoreExpenseRequest $request): JsonResponse|RedirectResponse
    {
        $result = $this->accountingService->createExpense($request->validated());

        if ($result->isError()) {
            return back()->withErrors(['error' => $result->getMessage()]);
        }

        return back()->with('success', $result->getMessage());
    }

    /**
     * Tạo doanh thu khác
     */
    public function storeRevenue(StoreRevenueRequest $request): JsonResponse|RedirectResponse
    {
        $result = $this->accountingService->createRevenue($request->validated());

        if ($result->isError()) {
            return back()->withErrors(['error' => $result->getMessage()]);
        }

        return back()->with('success', $result->getMessage());
    }

    /**
     * Lấy tổng hợp chi phí
     */
    public function getExpenseSummary(): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;
        $fromDate = request()->input('from_date', now()->startOfMonth()->toDateString());
        $toDate = request()->input('to_date', now()->endOfMonth()->toDateString());

        $result = $this->accountingService->getExpenseSummary($organizationId, $fromDate, $toDate);

        if ($result->isError()) {
            return response()->json(['error' => $result->getMessage()], 400);
        }

        return response()->json($result->getData());
    }
}

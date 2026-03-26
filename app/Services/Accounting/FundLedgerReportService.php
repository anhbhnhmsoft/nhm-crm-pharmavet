<?php

namespace App\Services\Accounting;

use App\Common\Constants\Organization\FundTransactionType;
use App\Models\FundTransaction;
use App\Repositories\FundTransactionAttachmentRepository;
use App\Repositories\FundTransactionRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FundLedgerReportService
{

    public function __construct(
        private FundTransactionAttachmentRepository $fundTransactionAttachmentRepository,
        private FundTransactionRepository $fundTransactionRepository,
    ) {
    }

    public function getLedger(array $filters): Collection
    {
        return $this->buildQuery($filters)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    public function getSummary(array $filters): array
    {
        $base = $this->buildQuery($filters);

        $totalIn = (float) (clone $base)->where('type', FundTransactionType::DEPOSIT->value)->sum('amount');
        $totalOut = (float) (clone $base)->where('type', FundTransactionType::WITHDRAW->value)->sum('amount');
        $balance = $totalIn - $totalOut;

        return [
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'balance' => $balance,
        ];
    }

    public function getCompareWithPreviousPeriod(array $filters): array
    {
        $fromDate = (string) ($filters['from_date'] ?? now()->startOfMonth()->toDateString());
        $toDate = (string) ($filters['to_date'] ?? now()->toDateString());

        $from = \Carbon\Carbon::parse($fromDate);
        $to = \Carbon\Carbon::parse($toDate);
        $days = max(1, $from->diffInDays($to) + 1);

        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        $current = $this->getSummary($filters);
        $previous = $this->getSummary(array_merge($filters, [
            'from_date' => $prevFrom->toDateString(),
            'to_date' => $prevTo->toDateString(),
        ]));

        return [
            'current' => $current,
            'previous' => $previous,
            'variance' => [
                'total_in' => $this->variancePercent($current['total_in'], $previous['total_in']),
                'total_out' => $this->variancePercent($current['total_out'], $previous['total_out']),
                'balance' => $this->variancePercent($current['balance'], $previous['balance']),
            ],
            'previous_range' => [$prevFrom->toDateString(), $prevTo->toDateString()],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $query = $this->fundTransactionRepository->query()->with('fund');
        $organizationId = (int) ($filters['organization_id'] ?? 0);
        $fromDate = (string) ($filters['from_date'] ?? now()->startOfMonth()->toDateString());
        $toDate = (string) ($filters['to_date'] ?? now()->toDateString());
        $fundId = (int) ($filters['fund_id'] ?? 0);
        $counterparty = trim((string) ($filters['counterparty_name'] ?? ''));

        if ($organizationId > 0) {
            $query->whereHas('fund', fn ($q) => $q->where('organization_id', $organizationId));
        }

        $query->whereBetween('transaction_date', [$fromDate, $toDate]);

        if ($fundId > 0) {
            $query->where('fund_id', $fundId);
        }

        if ($counterparty !== '') {
            $query->where('counterparty_name', 'like', '%' . $counterparty . '%');
        }

        return $query;
    }

    protected function variancePercent(float $current, float $previous): float
    {
        if (abs($previous) < 0.00001) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 2);
    }
}

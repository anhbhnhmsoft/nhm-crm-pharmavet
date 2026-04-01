<?php

namespace App\Services\Marketing;

use App\Common\Constants\Order\OrderStatus;
use App\Models\User;
use App\Repositories\CustomerRepository;
use App\Repositories\MarketingBudgetRepository;
use App\Repositories\MarketingSpendAttachmentRepository;
use App\Repositories\MarketingSpendRepository;
use App\Repositories\OrderRepository;
use Illuminate\Support\Carbon;

class MarketingBudgetService
{
    public function __construct(
        protected MarketingBudgetRepository $marketingBudgetRepository,
        protected MarketingSpendRepository $marketingSpendRepository,
        protected MarketingSpendAttachmentRepository $marketingSpendAttachmentRepository,
        protected CustomerRepository $customerRepository,
        protected OrderRepository $orderRepository,
    ) {
    }

    public function upsertDailyBudget(array $inputs, User $actor): void
    {
        $date = Carbon::parse((string) ($inputs['date'] ?? now()->toDateString()))->toDateString();
        $channel = trim((string) ($inputs['channel'] ?? 'unknown'));
        $campaign = trim((string) ($inputs['campaign'] ?? 'default'));

        $budget = $this->marketingBudgetRepository->query()->firstOrNew([
            'organization_id' => $actor->organization_id,
            'date' => $date,
            'channel' => $channel,
            'campaign' => $campaign,
        ]);
        $budget->budget_amount = (float) ($inputs['budget_amount'] ?? 0);
        $budget->save();

        $spend = $this->marketingSpendRepository->query()->firstOrNew([
            'organization_id' => $actor->organization_id,
            'date' => $date,
            'channel' => $channel,
            'campaign' => $campaign,
        ]);
        $spend->actual_spend = (float) ($inputs['actual_spend'] ?? 0);
        $spend->fee_amount = (float) ($inputs['fee_amount'] ?? 0);
        $spend->note = (string) ($inputs['note'] ?? '');
        $spend->save();

        $attachments = collect((array) ($inputs['attachment_path'] ?? []))
            ->filter(fn($value) => is_string($value) && $value !== '')
            ->values();

        if ($attachments->isNotEmpty()) {
            $nextVersion = (int) $this->marketingSpendAttachmentRepository->query()
                ->where('marketing_spend_id', $spend->id)
                ->max('version') + 1;

            foreach ($attachments as $path) {
                $this->marketingSpendAttachmentRepository->create([
                    'marketing_spend_id' => $spend->id,
                    'version' => max(1, $nextVersion),
                    'file_path' => $path,
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => now(),
                ]);
                $nextVersion++;
            }
        }
    }

    public function summarize(array $filters, User $viewer): array
    {
        [$fromDate, $toDate] = $this->resolveDateRange($filters);

        $budgetRows = $this->marketingBudgetRepository->query()
            ->where('organization_id', $viewer->organization_id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->when(!empty($filters['channel']), fn($query) => $query->where('channel', $filters['channel']))
            ->when(!empty($filters['campaign']), fn($query) => $query->where('campaign', $filters['campaign']))
            ->get()
            ->keyBy(fn($row) => $row->date->toDateString() . '|' . $row->channel . '|' . $row->campaign);

        $spendRows = $this->marketingSpendRepository->query()
            ->where('organization_id', $viewer->organization_id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->when(!empty($filters['channel']), fn($query) => $query->where('channel', $filters['channel']))
            ->when(!empty($filters['campaign']), fn($query) => $query->where('campaign', $filters['campaign']))
            ->get();

        $rows = $spendRows->map(function ($spend) use ($budgetRows, $viewer) {
            $key = $spend->date->toDateString() . '|' . $spend->channel . '|' . $spend->campaign;
            $budgetAmount = (float) ($budgetRows->get($key)->budget_amount ?? 0);

            $metrics = $this->buildOperationalMetrics(
                $viewer->organization_id,
                $spend->date->toDateString(),
                $spend->channel,
                $spend->campaign
            );

            $totalSpend = (float) $spend->actual_spend + (float) $spend->fee_amount;
            $roi = $totalSpend > 0 ? round(($metrics['new_revenue'] + $metrics['old_revenue']) / $totalSpend, 4) : 0.0;

            return [
                'date' => $spend->date->toDateString(),
                'channel' => $spend->channel,
                'campaign' => $spend->campaign,
                'budget_amount' => round($budgetAmount, 2),
                'actual_spend' => round((float) $spend->actual_spend, 2),
                'fee_amount' => round((float) $spend->fee_amount, 2),
                'valid_leads' => $metrics['valid_leads'],
                'cost_per_lead' => $metrics['valid_leads'] > 0 ? round($totalSpend / $metrics['valid_leads'], 2) : 0,
                'new_revenue' => $metrics['new_revenue'],
                'old_revenue' => $metrics['old_revenue'],
                'orders_count' => $metrics['orders_count'],
                'close_rate' => $metrics['close_rate'],
                'cancel_rate' => $metrics['cancel_rate'],
                'aov' => $metrics['aov'],
                'roi' => $roi,
                'status' => $this->resolveBudgetStatus($budgetAmount, $totalSpend, $roi),
            ];
        })->values();

        return [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'channel' => (string) ($filters['channel'] ?? ''),
                'campaign' => (string) ($filters['campaign'] ?? ''),
            ],
            'rows' => $rows->all(),
            'summary' => [
                'budget_amount' => round((float) $rows->sum('budget_amount'), 2),
                'actual_spend' => round((float) $rows->sum('actual_spend'), 2),
                'fee_amount' => round((float) $rows->sum('fee_amount'), 2),
                'valid_leads' => (int) $rows->sum('valid_leads'),
                'new_revenue' => round((float) $rows->sum('new_revenue'), 2),
                'old_revenue' => round((float) $rows->sum('old_revenue'), 2),
            ],
            'attachments' => $this->getAttachmentHistory($viewer->organization_id, $fromDate, $toDate),
        ];
    }

    public function getAttachmentHistory(int $organizationId, string $fromDate, string $toDate): array
    {
        return $this->marketingSpendAttachmentRepository->query()
            ->join('marketing_spends as ms', 'ms.id', '=', 'marketing_spend_attachments.marketing_spend_id')
            ->leftJoin('users', 'users.id', '=', 'marketing_spend_attachments.uploaded_by')
            ->where('ms.organization_id', $organizationId)
            ->whereBetween('ms.date', [$fromDate, $toDate])
            ->orderByDesc('marketing_spend_attachments.uploaded_at')
            ->orderByDesc('marketing_spend_attachments.id')
            ->selectRaw('marketing_spend_attachments.id as id')
            ->selectRaw('ms.date as spend_date')
            ->selectRaw('ms.channel as channel')
            ->selectRaw('ms.campaign as campaign')
            ->selectRaw('marketing_spend_attachments.version as version')
            ->selectRaw('marketing_spend_attachments.file_path as file_path')
            ->selectRaw('COALESCE(users.name, ?) as uploaded_by_name', [__('marketing.honor_board.unknown_entity')])
            ->selectRaw('marketing_spend_attachments.uploaded_at as uploaded_at')
            ->limit(50)
            ->get()
            ->map(fn($row) => [
                'id' => (int) $row->id,
                'spend_date' => (string) $row->spend_date,
                'channel' => (string) $row->channel,
                'campaign' => (string) $row->campaign,
                'version' => (int) $row->version,
                'file_path' => (string) $row->file_path,
                'uploaded_by' => (string) $row->uploaded_by_name,
                'uploaded_at' => $row->uploaded_at ? (string) $row->uploaded_at : null,
            ])
            ->all();
    }

    private function buildOperationalMetrics(int $organizationId, string $date, string $channel, string $campaign): array
    {
        $leadQuery = $this->customerRepository->query()
            ->where('organization_id', $organizationId)
            ->whereDate('created_at', $date)
            ->where('source', $channel)
            ->when($campaign !== '', fn($query) => $query->where('source_detail', $campaign));

        $validLeads = (clone $leadQuery)
            ->whereNotNull('phone')
            ->select('phone')
            ->groupBy('phone')
            ->get()
            ->count();

        $orders = $this->orderRepository->query()
            ->where('organization_id', $organizationId)
            ->whereDate('created_at', $date)
            ->whereIn('status', [
                OrderStatus::CONFIRMED->value,
                OrderStatus::SHIPPING->value,
                OrderStatus::COMPLETED->value,
                OrderStatus::CANCELLED->value,
            ])
            ->whereHas('customer', function ($query) use ($channel, $campaign) {
                $query->where('source', $channel)
                    ->when($campaign !== '', fn($query) => $query->where('source_detail', $campaign));
            })
            ->with('customer:id,phone')
            ->get();

        $completedOrders = $orders->where('status', OrderStatus::COMPLETED->value);
        $cancelledOrders = $orders->where('status', OrderStatus::CANCELLED->value);

        $newRevenue = 0.0;
        $oldRevenue = 0.0;
        foreach ($completedOrders as $order) {
            $phone = (string) ($order->customer?->phone ?? '');
            $hadCompletedBefore = $this->orderRepository->query()
                ->where('organization_id', $organizationId)
                ->where('status', OrderStatus::COMPLETED->value)
                ->where('id', '!=', $order->id)
                ->where('created_at', '<', $order->created_at)
                ->whereHas('customer', fn($query) => $query->where('phone', $phone))
                ->exists();

            if ($hadCompletedBefore) {
                $oldRevenue += (float) $order->total_amount;
            } else {
                $newRevenue += (float) $order->total_amount;
            }
        }

        $completedCount = $completedOrders->count();
        $orderCount = max(1, $orders->count());

        return [
            'valid_leads' => $validLeads,
            'new_revenue' => round($newRevenue, 2),
            'old_revenue' => round($oldRevenue, 2),
            'close_rate' => $validLeads > 0 ? round(($completedCount / $validLeads) * 100, 2) : 0,
            'cancel_rate' => round(($cancelledOrders->count() / $orderCount) * 100, 2),
            'aov' => $completedCount > 0 ? round((float) $completedOrders->sum('total_amount') / $completedCount, 2) : 0,
            'orders_count' => $orders->count(),
        ];
    }

    private function resolveDateRange(array $filters): array
    {
        $from = Carbon::parse((string) ($filters['from_date'] ?? now()->startOfMonth()->toDateString()));
        $to = Carbon::parse((string) ($filters['to_date'] ?? now()->toDateString()));

        if ($to->lt($from)) {
            $to = $from->copy();
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    private function resolveBudgetStatus(float $budgetAmount, float $totalSpend, float $roi): string
    {
        if ($budgetAmount > 0 && $totalSpend > $budgetAmount) {
            return 'over_budget';
        }

        if ($roi > 0 && $roi < 1) {
            return 'roi_low';
        }

        return 'ok';
    }
}

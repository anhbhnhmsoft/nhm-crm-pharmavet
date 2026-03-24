<?php

namespace App\Services\Telesale;

use App\Common\Constants\User\UserRole;
use App\Models\User;
use App\Repositories\TeamReportScopeRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TelesaleReportScopeService
{

    public function __construct(
        private TeamReportScopeRepository $teamReportScopeRepository,
    ) {
    }

    public function applyOrderScope(Builder $query, User $user, string $column = 'orders.created_by'): Builder
    {
        if ($user->role === UserRole::SUPER_ADMIN->value) {
            return $query;
        }

        $query->where('orders.organization_id', $user->organization_id);

        $staffIds = $this->resolveScopedStaffIds($user);
        if (is_array($staffIds)) {
            return $query->whereIn($column, $staffIds);
        }

        return $query;
    }

    public function resolveScopedStaffIds(User $user): ?array
    {
        if ($user->role === UserRole::SUPER_ADMIN->value) {
            return null;
        }

        if ($user->role === UserRole::SALE->value) {
            return [$user->id];
        }

        $scopeRows = $this->teamReportScopeRepository->query()
            ->where('organization_id', $user->organization_id)
            ->where('leader_id', $user->id)
            ->get(['team_id']);

        if ($scopeRows->isEmpty()) {
            return null;
        }

        $teamIds = $scopeRows->pluck('team_id')->filter()->unique()->values()->all();
        $staffIds = [];

        if (!empty($teamIds)) {
            $staffIds = DB::table('user_team')
                ->whereIn('team_id', $teamIds)
                ->pluck('user_id')
                ->unique()
                ->values()
                ->all();
        }

        $staffIds[] = $user->id;

        return array_values(array_unique($staffIds));
    }
}

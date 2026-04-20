<?php

namespace App\Repositories;

use App\Common\Constants\Organization\FundLockAction;
use App\Common\Constants\Organization\FundLockScope;
use App\Core\BaseRepository;
use App\Models\FundLockRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FundLockRuleRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new FundLockRule();
    }

    public function lockedRulesForAction(int $fundId, FundLockAction|string $action): Builder
    {
        $actionValue = $action instanceof FundLockAction ? $action->value : (string) $action;

        return $this->query()
            ->where('fund_id', $fundId)
            ->where('action', $actionValue)
            ->where('is_locked', true);
    }

    public function isActionLockedForUser(int $fundId, User $user, FundLockAction|string $action): bool
    {
        $teamIds = $user->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();

        return $this->lockedRulesForAction($fundId, $action)
            ->where(function (Builder $query) use ($user, $teamIds): void {
                $query->where('scope_type', FundLockScope::GLOBAL->value)
                    ->orWhere(function (Builder $userQuery) use ($user): void {
                        $userQuery
                            ->where('scope_type', FundLockScope::USER->value)
                            ->where('user_id', (int) $user->id);
                    });

                if ($teamIds !== []) {
                    $query->orWhere(function (Builder $teamQuery) use ($teamIds): void {
                        $teamQuery
                            ->where('scope_type', FundLockScope::TEAM->value)
                            ->whereIn('team_id', $teamIds);
                    });
                }
            })
            ->exists();
    }
}

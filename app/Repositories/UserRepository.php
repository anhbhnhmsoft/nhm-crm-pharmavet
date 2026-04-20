<?php

namespace App\Repositories;

use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Core\BaseRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserRepository extends BaseRepository
{
    public function model(): User
    {
        return new User();
    }

    public function getActiveSalarySumByOrganization(int $organizationId): float
    {
        return (float) $this->query()
            ->where('organization_id', $organizationId)
            ->where('disable', false)
            ->sum('salary');
    }

    public function getSaleLeaderOptionsByOrganization(?int $organizationId): array
    {
        return $this->query()
            ->when($organizationId, fn (Builder $query, int $orgId) => $query->where('organization_id', $orgId))
            ->where('role', UserRole::SALE->value)
            ->where('position', UserPosition::LEADER->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getDirectSaleLeaderTeamIds(?int $organizationId, int|string $leaderId): Collection
    {
        return $this->query()
            ->when($organizationId, fn (Builder $query, int $orgId) => $query->where('organization_id', $orgId))
            ->whereKey($leaderId)
            ->where('role', UserRole::SALE->value)
            ->where('position', UserPosition::LEADER->value)
            ->pluck('team_id');
    }

    public function getSaleOptionsByOrganization(?int $organizationId, array $teamIds = []): array
    {
        return $this->query()
            ->when($organizationId, fn (Builder $query, int $orgId) => $query->where('organization_id', $orgId))
            ->where('role', UserRole::SALE->value)
            ->when(
                $teamIds !== [],
                fn (Builder $query) => $query->where(function (Builder $teamQuery) use ($teamIds): void {
                    $teamQuery
                        ->whereIn('team_id', $teamIds)
                        ->orWhereHas('teams', fn (Builder $nestedQuery) => $nestedQuery->whereIn('teams.id', $teamIds));
                })
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}

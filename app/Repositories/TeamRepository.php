<?php

namespace App\Repositories;

use App\Common\Constants\Team\TeamType;
use App\Core\BaseRepository;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TeamRepository extends BaseRepository {

    public function model() : Model {
        return new Team();
    }

    public function getSaleLeaderTeamIds(?int $organizationId, int|string $leaderId): array
    {
        return $this->query()
            ->when($organizationId, fn (Builder $query, int $orgId) => $query->where('organization_id', $orgId))
            ->where('type', TeamType::SALE->value)
            ->whereHas('users', fn (Builder $query) => $query->where('users.id', $leaderId))
            ->pluck('id')
            ->map(fn ($teamId) => (int) $teamId)
            ->all();
    }

    public function getSaleTeamOptionsByOrganization(?int $organizationId, array $teamIds = []): array
    {
        return $this->query()
            ->when($organizationId, fn (Builder $query, int $orgId) => $query->where('organization_id', $orgId))
            ->where('type', TeamType::SALE->value)
            ->when($teamIds !== [], fn (Builder $query) => $query->whereIn('id', $teamIds))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}

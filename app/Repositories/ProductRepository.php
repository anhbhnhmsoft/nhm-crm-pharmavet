<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ProductRepository extends BaseRepository
{

    public function model(): Model
    {
        return new Product();
    }

    public function getNamesByOrganization(int $organizationId): Collection
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();
    }

    public function getIdsByOrganizationAndExactName(?int $organizationId, string $name): array
    {
        return $this->query()
            ->when($organizationId, fn (Builder $query, int $orgId) => $query->where('organization_id', $orgId))
            ->where('name', $name)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

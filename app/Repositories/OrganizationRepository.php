<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Organization;

class OrganizationRepository extends BaseRepository
{
    public function model(): Organization
    {
        return new Organization();
    }

    public function isForeignById(int $organizationId): bool
    {
        return (bool) $this->query()
            ->where('id', $organizationId)
            ->value('is_foreign');
    }
}

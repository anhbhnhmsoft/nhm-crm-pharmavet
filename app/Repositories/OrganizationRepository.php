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
}

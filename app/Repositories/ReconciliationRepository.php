<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Reconciliation;
use Illuminate\Database\Eloquent\Model;

class ReconciliationRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Reconciliation();
    }
}


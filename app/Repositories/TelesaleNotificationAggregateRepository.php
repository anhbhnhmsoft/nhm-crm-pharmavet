<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\TelesaleNotificationAggregate;
use Illuminate\Database\Eloquent\Model;

class TelesaleNotificationAggregateRepository extends BaseRepository
{
    public function model(): Model
    {
        return new TelesaleNotificationAggregate();
    }
}

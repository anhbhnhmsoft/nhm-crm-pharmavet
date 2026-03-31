<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\FacebookEventLog;
use Illuminate\Database\Eloquent\Model;

class FacebookEventLogRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new FacebookEventLog();
    }
}

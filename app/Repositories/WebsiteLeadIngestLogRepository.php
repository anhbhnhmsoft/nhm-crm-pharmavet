<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\WebsiteLeadIngestLog;
use Illuminate\Database\Eloquent\Model;

class WebsiteLeadIngestLogRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new WebsiteLeadIngestLog();
    }
}

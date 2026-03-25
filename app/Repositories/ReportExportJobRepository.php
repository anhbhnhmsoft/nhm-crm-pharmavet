<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\ReportExportJob;
use Illuminate\Database\Eloquent\Model;

class ReportExportJobRepository extends BaseRepository
{
    public function model(): Model
    {
        return new ReportExportJob();
    }
}

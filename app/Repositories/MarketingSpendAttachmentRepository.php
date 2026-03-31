<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\MarketingSpendAttachment;
use Illuminate\Database\Eloquent\Model;

class MarketingSpendAttachmentRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new MarketingSpendAttachment();
    }
}

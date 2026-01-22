<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\FundTransaction;
use Illuminate\Database\Eloquent\Model;

class FundTransactionRepository extends BaseRepository
{
    public function model(): Model
    {
        return new FundTransaction();
    }
}

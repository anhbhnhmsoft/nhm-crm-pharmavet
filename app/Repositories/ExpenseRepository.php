<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Model;

class ExpenseRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Expense();
    }
}


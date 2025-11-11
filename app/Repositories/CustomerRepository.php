<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;

class CustomerRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Customer();
    }
}

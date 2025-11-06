<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class ProductRepository  extends BaseRepository
{

    public function model(): Model
    {
        return new Product();
    }
}

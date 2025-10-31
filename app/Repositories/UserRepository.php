<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\User;

class UserRepository extends BaseRepository
{
    public function model(): User
    {
        return new User();
    }
}

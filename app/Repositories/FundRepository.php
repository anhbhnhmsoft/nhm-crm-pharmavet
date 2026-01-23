<?php namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Fund;
use App\Models\InventoryTicket;
use Illuminate\Database\Eloquent\Model;

class FundRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Fund();
    }
}

<?php

namespace App\Repositories\Accounting;

use App\Core\BaseRepository;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class DebtRepository extends BaseRepository
{
    protected function model(): Order
    {
        return new Order();
    }

    public function getBadDebtsQuery(int $organizationId): Builder
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->where('is_written_off', false)
            ->whereRaw('(collect_amount - amount_recived_from_customer) > 0');
    }

    public function getAgingQuery(int $organizationId, int $days): Builder
    {
        return $this->getBadDebtsQuery($organizationId)
            ->where('created_at', '<=', now()->subDays($days));
    }
}

<?php namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\FundLockRule;
use Illuminate\Database\Eloquent\Model;

class FundLockRuleRepository extends BaseRepository
{
    public function model(): Model
    {
        return new FundLockRule();
    }

    /**
     * Tính tổng số dư các quỹ của một tổ chức
     */
    public function sumTotalBalanceByOrganization(int $organizationId): float
    {
        return (float) $this->query()
            ->where('organization_id', $organizationId)
            ->sum('balance');
    }
}

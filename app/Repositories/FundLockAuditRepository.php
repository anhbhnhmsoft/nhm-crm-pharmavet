<?php namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\FundLockAudit;
use Illuminate\Database\Eloquent\Model;

class FundLockAuditRepository extends BaseRepository
{
    public function model(): Model
    {
        return new FundLockAudit();
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

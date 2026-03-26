<?php namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\FundTransactionAttachment;
use Illuminate\Database\Eloquent\Model;

class FundTransactionAttachmentRepository extends BaseRepository
{
    public function model(): Model
    {
        return new FundTransactionAttachment();
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

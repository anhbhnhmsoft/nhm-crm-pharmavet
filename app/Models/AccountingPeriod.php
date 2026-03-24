<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'organization_id',
        'month',
        'year',
        'closed_at',
        'closed_by',
        'note',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Kiểm tra xem một tháng/năm của tổ chức đã bị khóa chưa
     */
    public static function isClosed(int $organizationId, int $month, int $year): bool
    {
        return self::where('organization_id', $organizationId)
            ->where('month', $month)
            ->where('year', $year)
            ->whereNotNull('closed_at')
            ->exists();
    }
}

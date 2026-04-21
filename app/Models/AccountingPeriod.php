<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    public static function isDateClosed(int $organizationId, CarbonInterface|string|null $date): bool
    {
        if (blank($date)) {
            return false;
        }

        $resolvedDate = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return self::isClosed($organizationId, $resolvedDate->month, $resolvedDate->year);
    }
}

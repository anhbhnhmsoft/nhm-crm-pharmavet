<?php

namespace App\Utils;

use App\Models\AccountingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AccountingPeriodGuard
{
    public static function isClosedForDate(?int $organizationId, mixed $date): bool
    {
        if (! $organizationId) {
            return false;
        }

        $resolvedDate = self::parseDate($date);

        if (! $resolvedDate) {
            return false;
        }

        return AccountingPeriod::isClosed($organizationId, $resolvedDate->month, $resolvedDate->year);
    }

    public static function isClosedForRecord(Model $record, string | array $dateFields): bool
    {
        $dateFields = is_array($dateFields) ? $dateFields : [$dateFields];

        foreach ($dateFields as $field) {
            $value = data_get($record, $field);

            if (! blank($value)) {
                return self::isClosedForDate((int) data_get($record, 'organization_id'), $value);
            }
        }

        return self::isClosedForDate((int) data_get($record, 'organization_id'), now());
    }

    public static function parseDate(mixed $date): ?CarbonInterface
    {
        if ($date instanceof CarbonInterface) {
            return $date;
        }

        if (blank($date)) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }
}

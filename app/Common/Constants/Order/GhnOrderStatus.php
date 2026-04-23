<?php

namespace App\Common\Constants\Order;

use Illuminate\Support\Str;

enum GhnOrderStatus: string
{
    public const LEGACY_CANCELLED = 'cancelled';

    case READY_TO_PICK = 'ready_to_pick';
    case PICKING = 'picking';
    case CANCEL = 'cancel';
    case MONEY_COLLECT_PICKING = 'money_collect_picking';
    case PICKED = 'picked';
    case STORING = 'storing';
    case TRANSPORTING = 'transporting';
    case SORTING = 'sorting';
    case DELIVERING = 'delivering';
    case MONEY_COLLECT_DELIVERING = 'money_collect_delivering';
    case DELIVERED = 'delivered';
    case DELIVERY_FAIL = 'delivery_fail';
    case WAITING_TO_RETURN = 'waiting_to_return';
    case RETURN = 'return';
    case RETURN_TRANSPORTING = 'return_transporting';
    case RETURN_SORTING = 'return_sorting';
    case RETURNING = 'returning';
    case RETURN_FAIL = 'return_fail';
    case RETURNED = 'returned';
    case EXCEPTION = 'exception';
    case DAMAGE = 'damage';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::READY_TO_PICK => __('order.ghn_status.ready_to_pick'),
            self::PICKING => __('order.ghn_status.picking'),
            self::CANCEL => __('order.ghn_status.cancel'),
            self::MONEY_COLLECT_PICKING => __('order.ghn_status.money_collect_picking'),
            self::PICKED => __('order.ghn_status.picked'),
            self::STORING => __('order.ghn_status.storing'),
            self::TRANSPORTING => __('order.ghn_status.transporting'),
            self::SORTING => __('order.ghn_status.sorting'),
            self::DELIVERING => __('order.ghn_status.delivering'),
            self::MONEY_COLLECT_DELIVERING => __('order.ghn_status.money_collect_delivering'),
            self::DELIVERED => __('order.ghn_status.delivered'),
            self::DELIVERY_FAIL => __('order.ghn_status.delivery_fail'),
            self::WAITING_TO_RETURN => __('order.ghn_status.waiting_to_return'),
            self::RETURN => __('order.ghn_status.return'),
            self::RETURN_TRANSPORTING => __('order.ghn_status.return_transporting'),
            self::RETURN_SORTING => __('order.ghn_status.return_sorting'),
            self::RETURNING => __('order.ghn_status.returning'),
            self::RETURN_FAIL => __('order.ghn_status.return_fail'),
            self::RETURNED => __('order.ghn_status.returned'),
            self::EXCEPTION => __('order.ghn_status.exception'),
            self::DAMAGE => __('order.ghn_status.damage'),
            self::LOST => __('order.ghn_status.lost'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public static function resolveLabel(?string $state): string
    {
        if (empty($state)) {
            return '-';
        }

        if (self::isNotPosted($state)) {
            return __('order.table.not_posted');
        }

        $normalized = self::normalize($state);

        if ($normalized === null) {
            return '-';
        }

        return self::tryFrom($normalized)?->label() ?? strtoupper($normalized);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::READY_TO_PICK,
            self::PICKING,
            self::PICKED,
            self::STORING,
            self::TRANSPORTING,
            self::SORTING,
            self::DELIVERING,
            self::MONEY_COLLECT_PICKING,
            self::MONEY_COLLECT_DELIVERING => 'info',

            self::DELIVERED,
            self::RETURNED => 'success',

            self::DELIVERY_FAIL,
            self::WAITING_TO_RETURN,
            self::RETURN,
            self::RETURN_TRANSPORTING,
            self::RETURN_SORTING,
            self::RETURNING,
            self::RETURN_FAIL,
            self::EXCEPTION,
            self::DAMAGE,
            self::LOST,
            self::CANCEL => 'danger',
        };
    }

    public static function color(?string $state): string
    {
        if ($state === self::LEGACY_CANCELLED) {
            $state = self::CANCEL->value;
        }

        $normalized = self::normalize($state);

        if ($normalized === null) {
            return 'gray';
        }

        return self::tryFrom($normalized)?->getColor() ?? 'gray';
    }

    public static function toArray(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[$case->value] = $case->label();
        }

        return $array;
    }

    public static function toOptions(): array
    {
        return self::toArray();
    }

    public static function normalize(?string $state): ?string
    {
        $normalized = mb_strtolower(trim((string) $state));
        $lookupKey = self::normalizeLookupKey($state);

        if ($normalized === '') {
            return null;
        }

        if (self::isNotPosted($state)) {
            return null;
        }

        if ($normalized === self::LEGACY_CANCELLED) {
            return self::CANCEL->value;
        }

        if (self::tryFrom($normalized)) {
            return $normalized;
        }

        foreach (self::cases() as $case) {
            if ($lookupKey === self::normalizeLookupKey($case->label())) {
                return $case->value;
            }
        }

        return $normalized;
    }

    public static function displayLabel(?string $state, ?string $fallbackLabel = null): string
    {
        foreach ([$state, $fallbackLabel] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate === '') {
                continue;
            }

            if (self::isNotPosted($candidate)) {
                return __('order.table.not_posted');
            }

            $normalized = self::normalize($candidate);

            if ($normalized !== null) {
                return self::resolveLabel($normalized);
            }

            return $candidate;
        }

        return '-';
    }

    public static function isNotPosted(?string $state): bool
    {
        $normalized = self::normalizeLookupKey($state);

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, [
            'chua dang don',
            self::normalizeLookupKey(__('order.table.not_posted')),
        ], true);
    }

    public static function finalStatusesForReconciliation(): array
    {
        return [
            self::DELIVERED->value,
            self::RETURNED->value,
            self::CANCEL->value,
            self::LOST->value,
            self::DAMAGE->value,
            self::EXCEPTION->value,
        ];
    }

    public static function isFinalForReconciliation(?string $state): bool
    {
        return in_array(self::normalize($state), self::finalStatusesForReconciliation(), true);
    }

    public static function careRuleGroup(?string $state): ?GhnCareRuleGroup
    {
        return match (self::normalize($state)) {
            self::READY_TO_PICK->value,
            self::PICKING->value,
            self::MONEY_COLLECT_PICKING->value,
            self::PICKED->value,
            self::STORING->value,
            self::TRANSPORTING->value,
            self::SORTING->value => GhnCareRuleGroup::BEFORE_DELIVERY,

            self::DELIVERING->value,
            self::MONEY_COLLECT_DELIVERING->value => GhnCareRuleGroup::DELIVERING,

            self::DELIVERED->value => GhnCareRuleGroup::DELIVERED,

            self::DELIVERY_FAIL->value,
            self::WAITING_TO_RETURN->value,
            self::RETURN->value,
            self::RETURN_TRANSPORTING->value,
            self::RETURN_SORTING->value,
            self::RETURNING->value,
            self::RETURN_FAIL->value,
            self::RETURNED->value => GhnCareRuleGroup::RETURNING,

            self::CANCEL->value,
            self::EXCEPTION->value,
            self::DAMAGE->value,
            self::LOST->value => GhnCareRuleGroup::ABNORMAL,

            default => null,
        };
    }

    private static function normalizeLookupKey(?string $state): string
    {
        $normalized = mb_strtolower(trim(strip_tags((string) $state)));

        if ($normalized === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', Str::ascii($normalized)) ?? '';
    }
}

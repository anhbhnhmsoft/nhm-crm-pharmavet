<?php

namespace App\Common\Constants\Order;

enum OrderCareStatus: int
{
    case IMMEDIATE_DELIVERY = 1;
    case WAITING_DELIVERY = 2;
    case DELAYED_DELIVERY = 3;
    case SALE_RESCUED_ORDER = 4;
    case CUSTOMER_COMPLAINT = 5;
    case COMPLAINT_RESOLVED = 6;
    case REDELIVERY = 7;
    case STORED = 8;
    case NO_ANSWER = 9;
    case REDELIVERY_REQUESTED = 10;
    case CUSTOMER_REFUSED = 11;
    case RETURN_REPORTED = 12;
    case NEED_RECHECK = 13;
    case PRINTED = 14;
    case OUT_FOR_DELIVERY = 15;
    case OUT_FOR_DELIVERY_RECEIVED = 16;
    case OUT_FOR_DELIVERY_RETURN = 17;
    case RECEIVED = 18;
    case RETURNED_ORDER = 19;
    case DUPLICATE_ORDER = 20;

    public function label(): string
    {
        return self::getLabel($this->value);
    }

    public static function getLabel(?int $status): string
    {
        return match ($status) {
            self::IMMEDIATE_DELIVERY->value => __('order.care_status.immediate_delivery'),
            self::WAITING_DELIVERY->value => __('order.care_status.waiting_delivery'),
            self::DELAYED_DELIVERY->value => __('order.care_status.delayed_delivery'),
            self::SALE_RESCUED_ORDER->value => __('order.care_status.sale_rescued_order'),
            self::CUSTOMER_COMPLAINT->value => __('order.care_status.customer_complaint'),
            self::COMPLAINT_RESOLVED->value => __('order.care_status.complaint_resolved'),
            self::REDELIVERY->value => __('order.care_status.redelivery'),
            self::STORED->value => __('order.care_status.stored'),
            self::NO_ANSWER->value => __('order.care_status.no_answer'),
            self::REDELIVERY_REQUESTED->value => __('order.care_status.redelivery_requested'),
            self::CUSTOMER_REFUSED->value => __('order.care_status.customer_refused'),
            self::RETURN_REPORTED->value => __('order.care_status.return_reported'),
            self::NEED_RECHECK->value => __('order.care_status.need_recheck'),
            self::PRINTED->value => __('order.care_status.printed'),
            self::OUT_FOR_DELIVERY->value => __('order.care_status.out_for_delivery'),
            self::OUT_FOR_DELIVERY_RECEIVED->value => __('order.care_status.out_for_delivery_received'),
            self::OUT_FOR_DELIVERY_RETURN->value => __('order.care_status.out_for_delivery_return'),
            self::RECEIVED->value => __('order.care_status.received'),
            self::RETURNED_ORDER->value => __('order.care_status.returned_order'),
            self::DUPLICATE_ORDER->value => __('order.care_status.duplicate_order'),
            default => __('order.care_status.not_cared'),
        };
    }

    public static function toOptions(): array
    {
        return [
            self::IMMEDIATE_DELIVERY->value => __('order.care_status.immediate_delivery'),
            self::WAITING_DELIVERY->value => __('order.care_status.waiting_delivery'),
            self::DELAYED_DELIVERY->value => __('order.care_status.delayed_delivery'),
            self::SALE_RESCUED_ORDER->value => __('order.care_status.sale_rescued_order'),
            self::CUSTOMER_COMPLAINT->value => __('order.care_status.customer_complaint'),
            self::COMPLAINT_RESOLVED->value => __('order.care_status.complaint_resolved'),
            self::REDELIVERY->value => __('order.care_status.redelivery'),
            self::STORED->value => __('order.care_status.stored'),
            self::NO_ANSWER->value => __('order.care_status.no_answer'),
            self::REDELIVERY_REQUESTED->value => __('order.care_status.redelivery_requested'),
            self::CUSTOMER_REFUSED->value => __('order.care_status.customer_refused'),
            self::RETURN_REPORTED->value => __('order.care_status.return_reported'),
            self::NEED_RECHECK->value => __('order.care_status.need_recheck'),
            self::PRINTED->value => __('order.care_status.printed'),
            self::OUT_FOR_DELIVERY->value => __('order.care_status.out_for_delivery'),
            self::OUT_FOR_DELIVERY_RECEIVED->value => __('order.care_status.out_for_delivery_received'),
            self::OUT_FOR_DELIVERY_RETURN->value => __('order.care_status.out_for_delivery_return'),
            self::RECEIVED->value => __('order.care_status.received'),
            self::RETURNED_ORDER->value => __('order.care_status.returned_order'),
            self::DUPLICATE_ORDER->value => __('order.care_status.duplicate_order'),
        ];
    }

    public static function color(?int $status): string
    {
        return match ($status) {
            null => 'text-gray-500',
            self::RECEIVED->value,
            self::COMPLAINT_RESOLVED->value => 'text-success-600',
            self::CUSTOMER_COMPLAINT->value,
            self::NEED_RECHECK->value,
            self::NO_ANSWER->value => 'text-warning-600',
            self::CUSTOMER_REFUSED->value,
            self::RETURN_REPORTED->value,
            self::RETURNED_ORDER->value,
            self::DUPLICATE_ORDER->value => 'text-danger-600',
            default => 'text-primary-600',
        };
    }

    public static function allowedValuesForShippingStatus(?string $shippingStatus): ?array
    {
        return match (GhnOrderStatus::careRuleGroup($shippingStatus)) {
            GhnCareRuleGroup::BEFORE_DELIVERY => [
                null,
                self::IMMEDIATE_DELIVERY->value,
                self::WAITING_DELIVERY->value,
                self::NEED_RECHECK->value,
                self::PRINTED->value,
            ],
            GhnCareRuleGroup::DELIVERING => [
                self::IMMEDIATE_DELIVERY->value,
                self::WAITING_DELIVERY->value,
                self::DELAYED_DELIVERY->value,
                self::NO_ANSWER->value,
                self::CUSTOMER_REFUSED->value,
                self::REDELIVERY_REQUESTED->value,
                self::NEED_RECHECK->value,
            ],
            GhnCareRuleGroup::DELIVERED => [
                self::RECEIVED->value,
                self::OUT_FOR_DELIVERY_RECEIVED->value,
                self::COMPLAINT_RESOLVED->value,
            ],
            GhnCareRuleGroup::RETURNING => [
                self::RETURN_REPORTED->value,
                self::REDELIVERY_REQUESTED->value,
                self::REDELIVERY->value,
                self::SALE_RESCUED_ORDER->value,
                self::RETURNED_ORDER->value,
                self::OUT_FOR_DELIVERY_RETURN->value,
                self::NEED_RECHECK->value,
            ],
            GhnCareRuleGroup::ABNORMAL => [
                self::DUPLICATE_ORDER->value,
                self::NEED_RECHECK->value,
                self::CUSTOMER_COMPLAINT->value,
                self::COMPLAINT_RESOLVED->value,
            ],
            default => null,
        };
    }

    public static function allowedOptionsForShippingStatus(?string $shippingStatus, ?int $currentStatus = null): array
    {
        $allowedValues = self::allowedValuesForShippingStatus($shippingStatus);

        if ($allowedValues === null) {
            $options = self::toOptions();
        } else {
            $options = collect($allowedValues)
                ->filter(fn ($value) => $value !== null)
                ->mapWithKeys(fn (int $value): array => [$value => self::getLabel($value)])
                ->all();
        }

        if ($currentStatus !== null && ! array_key_exists($currentStatus, $options)) {
            $options[$currentStatus] = self::getLabel($currentStatus);
        }

        return $options;
    }

    public static function isAllowedForShippingStatus(?int $careStatus, ?string $shippingStatus): bool
    {
        $allowedValues = self::allowedValuesForShippingStatus($shippingStatus);

        if ($allowedValues === null) {
            return true;
        }

        return in_array($careStatus, $allowedValues, true);
    }

    public static function suggestedForShippingStatus(?string $shippingStatus): ?int
    {
        return match (GhnOrderStatus::careRuleGroup($shippingStatus)) {
            GhnCareRuleGroup::DELIVERED => self::RECEIVED->value,
            GhnCareRuleGroup::RETURNING => self::RETURNED_ORDER->value,
            GhnCareRuleGroup::ABNORMAL => self::NEED_RECHECK->value,
            default => null,
        };
    }
}

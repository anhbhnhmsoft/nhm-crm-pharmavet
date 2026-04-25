<?php

namespace App\Support;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\DistributionMethod;
use App\Common\Constants\Team\TeamType;

class LeadDistributionConfigRuleMatrix
{
    /**
     * @return list<int>
     */
    public static function allowedCustomerTypes(): array
    {
        return array_map(
            static fn (CustomerType $customerType): int => $customerType->value,
            CustomerType::cases(),
        );
    }

    /**
     * @return list<int>
     */
    public static function allowedStaffTypes(): array
    {
        return [
            TeamType::SALE->value,
            TeamType::CSKH->value,
        ];
    }

    public static function defaultDistributionMethod(): int
    {
        return DistributionMethod::MOST_RECENT_RECIPIENT->value;
    }

    /**
     * @return list<int>
     */
    public static function allowedDistributionMethods(): array
    {
        return array_map(
            static fn (DistributionMethod $method): int => $method->value,
            DistributionMethod::cases(),
        );
    }

    public static function buildKey(int|string|null $customerType, int|string|null $staffType): string
    {
        return sprintf('%s:%s', (string) $customerType, (string) $staffType);
    }

    /**
     * @return list<string>
     */
    public static function expectedKeys(): array
    {
        $keys = [];

        foreach (self::allowedCustomerTypes() as $customerType) {
            foreach (self::allowedStaffTypes() as $staffType) {
                $keys[] = self::buildKey($customerType, $staffType);
            }
        }

        return $keys;
    }

    public static function expectedRuleCount(): int
    {
        return count(self::expectedKeys());
    }

    /**
     * @return list<array{customer_type:int,staff_type:int,distribution_method:int}>
     */
    public static function defaultRules(): array
    {
        $rules = [];

        foreach (self::allowedCustomerTypes() as $customerType) {
            foreach (self::allowedStaffTypes() as $staffType) {
                $rules[] = [
                    'customer_type' => $customerType,
                    'staff_type' => $staffType,
                    'distribution_method' => self::defaultDistributionMethod(),
                ];
            }
        }

        return $rules;
    }

    /**
     * @param  iterable<array<string, mixed>>  $rules
     * @return list<array{customer_type:int,staff_type:int,distribution_method:int}>
     */
    public static function normalizeForForm(iterable $rules): array
    {
        $existingRules = [];

        foreach ($rules as $rule) {
            $customerType = (int) ($rule['customer_type'] ?? 0);
            $staffType = (int) ($rule['staff_type'] ?? 0);

            if (! self::isAllowedCustomerType($customerType) || ! self::isAllowedStaffType($staffType)) {
                continue;
            }

            $key = self::buildKey($customerType, $staffType);

            if (isset($existingRules[$key])) {
                continue;
            }

            $distributionMethod = (int) ($rule['distribution_method'] ?? self::defaultDistributionMethod());

            $existingRules[$key] = [
                'customer_type' => $customerType,
                'staff_type' => $staffType,
                'distribution_method' => self::isAllowedDistributionMethod($distributionMethod)
                    ? $distributionMethod
                    : self::defaultDistributionMethod(),
            ];
        }

        return array_map(
            static function (array $defaultRule) use ($existingRules): array {
                $key = self::buildKey($defaultRule['customer_type'], $defaultRule['staff_type']);

                return $existingRules[$key] ?? $defaultRule;
            },
            self::defaultRules(),
        );
    }

    /**
     * @param  iterable<array<string, mixed>>  $rules
     * @return array<string, list<string>>
     */
    public static function validationErrors(iterable $rules): array
    {
        $errors = [];
        $seenKeys = [];

        foreach (array_values(is_array($rules) ? $rules : iterator_to_array($rules)) as $index => $rule) {
            $customerType = (int) ($rule['customer_type'] ?? 0);
            $staffType = (int) ($rule['staff_type'] ?? 0);
            $distributionMethod = (int) ($rule['distribution_method'] ?? 0);

            if (! self::isAllowedCustomerType($customerType)) {
                $errors["rules.{$index}.customer_type"][] = __('common.error.in', [
                    'attribute' => __('filament.lead.customer.label'),
                ]);
            }

            if (! self::isAllowedStaffType($staffType)) {
                $errors["rules.{$index}.staff_type"][] = __('filament.lead.rule.validation.invalid_staff_type');
            }

            if (! self::isAllowedDistributionMethod($distributionMethod)) {
                $errors["rules.{$index}.distribution_method"][] = __('common.error.in', [
                    'attribute' => __('filament.lead.distribution.label'),
                ]);
            }

            if (! self::isAllowedCustomerType($customerType) || ! self::isAllowedStaffType($staffType)) {
                continue;
            }

            $key = self::buildKey($customerType, $staffType);

            if (isset($seenKeys[$key])) {
                $message = __('filament.lead.rule.validation.duplicate_key', [
                    'customer' => CustomerType::getLabel($customerType),
                    'staff' => TeamType::getLabel($staffType),
                ]);

                $errors["rules.{$seenKeys[$key]}.distribution_method"][] = $message;
                $errors["rules.{$index}.distribution_method"][] = $message;
                continue;
            }

            $seenKeys[$key] = $index;
        }

        if ($errors !== []) {
            return $errors;
        }

        $missingKeys = array_diff(self::expectedKeys(), array_keys($seenKeys));

        if ($missingKeys !== []) {
            $errors['rules'][] = __('filament.lead.rule.validation.invalid_matrix');
        }

        if (count($seenKeys) !== self::expectedRuleCount()) {
            $errors['rules'][] = __('filament.lead.rule.validation.invalid_matrix');
        }

        return $errors;
    }

    public static function isAllowedCustomerType(int $customerType): bool
    {
        return in_array($customerType, self::allowedCustomerTypes(), true);
    }

    public static function isAllowedStaffType(int $staffType): bool
    {
        return in_array($staffType, self::allowedStaffTypes(), true);
    }

    public static function isAllowedDistributionMethod(int $distributionMethod): bool
    {
        return in_array($distributionMethod, self::allowedDistributionMethods(), true);
    }
}

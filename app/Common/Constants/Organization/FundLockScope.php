<?php

namespace App\Common\Constants\Organization;

enum FundLockScope: string
{
    case GLOBAL = 'global';
    case USER = 'user';
    case TEAM = 'team';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => __('accounting.fund_lock.scopes.global'),
            self::USER => __('accounting.fund_lock.scopes.user'),
            self::TEAM => __('accounting.fund_lock.scopes.team'),
        };
    }

    public static function options(): array
    {
        return [
            self::GLOBAL->value => self::GLOBAL->label(),
            self::USER->value => self::USER->label(),
            self::TEAM->value => self::TEAM->label(),
        ];
    }
}

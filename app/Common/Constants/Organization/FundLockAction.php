<?php

namespace App\Common\Constants\Organization;

enum FundLockAction: string
{
    case ADD = 'add';
    case EDIT = 'edit';
    case DELETE = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::ADD => __('accounting.fund_lock.actions.add'),
            self::EDIT => __('accounting.fund_lock.actions.edit'),
            self::DELETE => __('accounting.fund_lock.actions.delete'),
        };
    }

    public static function options(): array
    {
        return [
            self::ADD->value => self::ADD->label(),
            self::EDIT->value => self::EDIT->label(),
            self::DELETE->value => self::DELETE->label(),
        ];
    }
}

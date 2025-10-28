<?php

namespace App\Common\Constants\Navigation;

enum NavigationGroup
{
    case Marketing;
    public function getLabel(): string
    {
        return match ($this) {
            self::Marketing => __('navigation-groups.shop'),
        };
    }
}

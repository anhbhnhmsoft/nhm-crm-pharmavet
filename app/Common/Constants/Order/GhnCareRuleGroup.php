<?php

namespace App\Common\Constants\Order;

enum GhnCareRuleGroup: string
{
    case BEFORE_DELIVERY = 'before_delivery';
    case DELIVERING = 'delivering';
    case DELIVERED = 'delivered';
    case RETURNING = 'returning';
    case ABNORMAL = 'abnormal';
}

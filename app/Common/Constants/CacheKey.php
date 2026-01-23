<?php

namespace App\Common\Constants;

enum CacheKey: string
{
    case USER_PERMISSIONS = 'user.permissions';
    case FILE_STORAGE     = 'file.storage';
    case GHN_ORDER_DETAIL = 'ghn.order_detail';
}

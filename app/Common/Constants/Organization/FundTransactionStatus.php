<?php

namespace App\Common\Constants\Organization;

enum FundTransactionStatus: int
{
    case PENDING = 1;
    case COMPLETED = 2;
    case CANCELLED = 3;
}

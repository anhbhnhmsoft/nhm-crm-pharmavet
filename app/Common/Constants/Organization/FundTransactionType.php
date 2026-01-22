<?php

namespace App\Common\Constants\Organization;

enum FundTransactionType: int
{
    case DEPOSIT = 2;
    case WITHDRAW = 3;
}

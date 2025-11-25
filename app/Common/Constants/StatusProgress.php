<?php

namespace App\Common\Constants;

enum StatusProgress: int
{
    case IN_PROGRESS = 1;
    case PEDING = 2;
    case COMPLETED = 3;
    case FAILED = 4;
}

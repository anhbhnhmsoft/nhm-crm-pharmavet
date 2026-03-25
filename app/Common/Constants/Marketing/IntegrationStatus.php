<?php

namespace App\Common\Constants\Marketing;

enum IntegrationStatus: int
{
    case PENDING = 0;
    case CONNECTED = 1;
    case EXPIRED = 2;

    case ERROR = 3;
}

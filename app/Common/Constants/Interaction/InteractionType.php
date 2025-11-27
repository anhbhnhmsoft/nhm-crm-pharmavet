<?php

namespace App\Common\Constants\Interaction;

enum InteractionType: int
{
    case CALL = 1;
    case SMS = 2;
    case EMAIL = 3;
    case NOTE = 4;
    case MEETING = 5;
}
<?php

namespace App\Enums;

enum TestStatusEnum: int
{
    case PENDING = 1;
    case ACTIVE = 2;
    case DISABLED = 3;
}
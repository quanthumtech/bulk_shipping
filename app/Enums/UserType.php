<?php

namespace App\Enums;

enum UserType: string
{
    case SuperAdmin = "1";
    case Admin      = "2";
    case User       = "3";
    case Developer  = "4";
}
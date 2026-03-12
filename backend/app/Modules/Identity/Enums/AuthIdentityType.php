<?php

namespace App\Modules\Identity\Enums;

enum AuthIdentityType: string
{
    case Phone = 'phone';
    case Eds = 'eds';
}

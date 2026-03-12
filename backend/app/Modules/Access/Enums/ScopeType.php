<?php

namespace App\Modules\Access\Enums;

enum ScopeType: string
{
    case Global = 'global';
    case Region = 'region';
    case District = 'district';
    case School = 'school';
}

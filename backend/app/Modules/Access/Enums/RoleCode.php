<?php

namespace App\Modules\Access\Enums;

enum RoleCode: string
{
    case Teacher = 'teacher';
    case Director = 'director';
    case Kitchen = 'kitchen';
    case Library = 'library';
    case DistrictOperator = 'district_operator';
    case RegionOperator = 'region_operator';
    case SuperAdmin = 'super_admin';
    case SupportAdmin = 'support_admin';
}


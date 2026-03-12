<?php

namespace App\Modules\Organizations\Application\DTO;

readonly class SchoolContext
{
    public function __construct(
        public int $regionId,
        public int $districtId,
        public int $schoolId,
    ) {
    }
}

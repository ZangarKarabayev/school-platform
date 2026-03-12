<?php

namespace App\Modules\Identity\Application\DTO;

readonly class EdsChallengeData
{
    public function __construct(
        public string $deviceName = 'web',
    ) {
    }
}

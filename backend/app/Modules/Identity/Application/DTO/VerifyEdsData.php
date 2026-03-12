<?php

namespace App\Modules\Identity\Application\DTO;

readonly class VerifyEdsData
{
    public function __construct(
        public int $challengeId,
        public string $signature,
        public string $lastName,
        public string $firstName,
        public ?string $middleName = null,
        public string $deviceName = 'web',
    ) {
    }
}

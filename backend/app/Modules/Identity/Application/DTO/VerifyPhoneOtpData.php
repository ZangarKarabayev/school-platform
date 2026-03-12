<?php

namespace App\Modules\Identity\Application\DTO;

readonly class VerifyPhoneOtpData
{
    public function __construct(
        public string $phone,
        public string $code,
        public string $purpose = 'login',
        public string $deviceName = 'mobile',
    ) {
    }
}

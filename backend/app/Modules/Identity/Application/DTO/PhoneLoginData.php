<?php

namespace App\Modules\Identity\Application\DTO;

readonly class PhoneLoginData
{
    public function __construct(
        public string $phone,
        public string $purpose = 'login',
    ) {
    }
}

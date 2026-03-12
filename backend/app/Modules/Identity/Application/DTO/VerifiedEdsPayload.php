<?php

namespace App\Modules\Identity\Application\DTO;

readonly class VerifiedEdsPayload
{
    public function __construct(
        public string $lastName,
        public string $firstName,
        public ?string $middleName,
        public string $certificateThumbprint,
        public ?string $certificateSerial = null,
        public ?string $subjectDn = null,
        public ?string $issuerDn = null,
        public ?string $validFrom = null,
        public ?string $validTo = null,
    ) {
    }
}

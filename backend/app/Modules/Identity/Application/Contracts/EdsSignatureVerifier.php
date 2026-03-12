<?php

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\DTO\VerifyEdsData;
use App\Modules\Identity\Application\DTO\VerifiedEdsPayload;
use App\Modules\Identity\Models\EdsChallenge;

interface EdsSignatureVerifier
{
    public function verify(EdsChallenge $challenge, VerifyEdsData $data): VerifiedEdsPayload;
}

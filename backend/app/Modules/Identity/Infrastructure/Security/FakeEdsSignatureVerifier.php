<?php

namespace App\Modules\Identity\Infrastructure\Security;

use App\Modules\Identity\Application\Contracts\EdsSignatureVerifier;
use App\Modules\Identity\Application\DTO\VerifyEdsData;
use App\Modules\Identity\Application\DTO\VerifiedEdsPayload;
use App\Modules\Identity\Models\EdsChallenge;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class FakeEdsSignatureVerifier implements EdsSignatureVerifier
{
    public function verify(EdsChallenge $challenge, VerifyEdsData $data): VerifiedEdsPayload
    {
        $expectedSignature = hash('sha256', implode('|', [
            $challenge->challenge,
            $data->lastName,
            $data->firstName,
            $data->middleName ?? '',
        ]));

        if (! hash_equals($expectedSignature, $data->signature)) {
            throw new UnprocessableEntityHttpException('Invalid EDS signature.');
        }

        return new VerifiedEdsPayload(
            lastName: $data->lastName,
            firstName: $data->firstName,
            middleName: $data->middleName,
            certificateThumbprint: hash('sha256', implode('|', [
                $data->lastName,
                $data->firstName,
                $data->middleName ?? '',
            ])),
        );
    }
}

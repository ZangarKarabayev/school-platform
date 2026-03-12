<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Contracts\EdsSignatureVerifier;
use App\Modules\Identity\Application\DTO\VerifyEdsData;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ValidateEdsChallengeAction
{
    public function __construct(
        private readonly EdsSignatureVerifier $verifier,
    ) {
    }

    public function execute(VerifyEdsData $data): array
    {
        $challenge = \App\Modules\Identity\Models\EdsChallenge::query()->find($data->challengeId);

        if (! $challenge || $challenge->expires_at->isPast() || $challenge->consumed_at !== null) {
            throw new UnprocessableEntityHttpException('Invalid or expired EDS challenge.');
        }

        return [
            'challenge' => $challenge,
            'verified' => $this->verifier->verify($challenge, $data),
        ];
    }
}

<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTO\EdsChallengeData;
use App\Modules\Identity\Models\EdsChallenge;
use Illuminate\Support\Str;

class StartEdsLoginAction
{
    public function execute(EdsChallengeData $data): array
    {
        $challenge = EdsChallenge::query()->create([
            'challenge' => Str::uuid()->toString().'|'.Str::random(32),
            'expires_at' => now()->addMinutes((int) config('services.eds_auth.challenge_ttl_minutes', 5)),
            'meta' => [
                'device_name' => $data->deviceName,
            ],
        ]);

        return [
            'challenge_id' => $challenge->id,
            'challenge' => $challenge->challenge,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ];
    }
}

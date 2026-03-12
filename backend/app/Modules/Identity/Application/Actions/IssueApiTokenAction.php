<?php

namespace App\Modules\Identity\Application\Actions;

use App\Models\User;
use App\Modules\Identity\Models\ApiToken;
use Illuminate\Support\Str;

class IssueApiTokenAction
{
    public function execute(User $user, string $deviceName = 'mobile'): array
    {
        $plainTextToken = Str::random(80);
        $expiresAt = now()->addMinutes((int) config('services.phone_auth.token_ttl_minutes', 43200));

        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $deviceName,
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'token_type' => 'Bearer',
        ];
    }
}

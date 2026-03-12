<?php

namespace App\Modules\Identity\Application\Actions;

use App\Models\User;
use App\Modules\Identity\Application\DTO\VerifyPhoneOtpData;
use App\Modules\Identity\Enums\AuthIdentityType;
use App\Modules\Identity\Models\AuthIdentity;
use Illuminate\Support\Facades\DB;

class VerifyPhoneOtpAction
{
    public function __construct(
        private readonly ValidatePhoneOtpAction $validatePhoneOtpAction,
        private readonly IssueApiTokenAction $issueApiTokenAction,
    ) {
    }

    public function execute(VerifyPhoneOtpData $data): array
    {
        $otp = $this->validatePhoneOtpAction->execute($data);

        return DB::transaction(function () use ($data, $otp): array {
            $user = User::query()->firstOrCreate(
                ['phone' => $data->phone],
                [
                    'first_name' => 'Pending',
                    'last_name' => preg_replace('/[^0-9]/', '', $data->phone) ?: 'User',
                    'status' => 'active',
                    'preferred_locale' => $this->preferredLocale(),
                ],
            );

            AuthIdentity::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => AuthIdentityType::Phone->value,
                    'phone' => $data->phone,
                ],
                [
                    'last_verified_at' => now(),
                ],
            );

            $otp->forceFill(['used_at' => now()])->save();
            $user->forceFill(['last_login_at' => now()])->save();

            return [
                'user' => $user->fresh(['roles', 'scopes']),
                'token' => $this->issueApiTokenAction->execute($user, $data->deviceName),
            ];
        });
    }

    private function preferredLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, ['ru', 'kk'], true) ? $locale : 'ru';
    }
}

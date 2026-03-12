<?php

namespace App\Modules\Identity\Application\Actions;

use App\Models\User;
use App\Modules\Identity\Application\DTO\VerifyEdsData;
use App\Modules\Identity\Enums\AuthIdentityType;
use App\Modules\Identity\Models\AuthIdentity;
use Illuminate\Support\Facades\DB;

class VerifyEdsLoginAction
{
    public function __construct(
        private readonly ValidateEdsChallengeAction $validateEdsChallengeAction,
        private readonly IssueApiTokenAction $issueApiTokenAction,
    ) {
    }

    public function execute(VerifyEdsData $data): array
    {
        ['challenge' => $challenge, 'verified' => $verified] = $this->validateEdsChallengeAction->execute($data);

        return DB::transaction(function () use ($challenge, $verified, $data): array {
            $identity = AuthIdentity::query()
                ->where('type', AuthIdentityType::Eds->value)
                ->where(function ($query) use ($verified): void {
                    $query->where('certificate_thumbprint', $verified->certificateThumbprint);

                    if ($verified->certificateSerial) {
                        $query->orWhere('certificate_serial', $verified->certificateSerial);
                    }
                })
                ->first();

            $user = $identity?->user
                ?? User::query()->create([
                    'first_name' => $verified->firstName,
                    'last_name' => $verified->lastName,
                    'middle_name' => $verified->middleName,
                    'status' => 'active',
                    'preferred_locale' => $this->preferredLocale(),
                ]);

            AuthIdentity::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => AuthIdentityType::Eds->value,
                ],
                [
                    'certificate_thumbprint' => $verified->certificateThumbprint,
                    'certificate_serial' => $verified->certificateSerial,
                    'subject_dn' => $verified->subjectDn,
                    'issuer_dn' => $verified->issuerDn,
                    'valid_from' => $verified->validFrom,
                    'valid_to' => $verified->validTo,
                    'last_verified_at' => now(),
                ],
            );

            $challenge->forceFill([
                'verified_at' => now(),
                'consumed_at' => now(),
            ])->save();

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

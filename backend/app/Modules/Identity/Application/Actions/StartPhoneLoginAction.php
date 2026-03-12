<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTO\PhoneLoginData;
use App\Modules\Identity\Models\OtpCode;
use Illuminate\Support\Facades\Hash;

class StartPhoneLoginAction
{
    public function execute(PhoneLoginData $data): array
    {
        $otpLength = (int) config('services.phone_auth.otp_length', 6);
        $code = str_pad((string) random_int(0, (10 ** $otpLength) - 1), $otpLength, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes((int) config('services.phone_auth.otp_ttl_minutes', 5));

        OtpCode::query()
            ->where('phone', $data->phone)
            ->where('purpose', $data->purpose)
            ->delete();

        OtpCode::query()->create([
            'phone' => $data->phone,
            'purpose' => $data->purpose,
            'code' => Hash::make($code),
            'expires_at' => $expiresAt,
        ]);

        return [
            'phone' => $data->phone,
            'purpose' => $data->purpose,
            'status' => 'pending_otp',
            'expires_at' => $expiresAt->toIso8601String(),
            'debug_code' => app()->environment(['local', 'testing']) ? $code : null,
        ];
    }
}

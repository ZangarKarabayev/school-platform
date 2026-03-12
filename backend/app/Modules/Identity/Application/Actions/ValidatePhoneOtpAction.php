<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTO\VerifyPhoneOtpData;
use App\Modules\Identity\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ValidatePhoneOtpAction
{
    public function execute(VerifyPhoneOtpData $data): OtpCode
    {
        $otp = OtpCode::query()
            ->where('phone', $data->phone)
            ->where('purpose', $data->purpose)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->expires_at->isPast() || ! Hash::check($data->code, $otp->code)) {
            throw new UnprocessableEntityHttpException('Invalid or expired OTP code.');
        }

        return $otp;
    }
}

<?php

namespace App\Modules\Identity\Http\Requests;

class VerifyPhoneOtpRequest extends LoginByPasswordRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{11,15}$/'],
            'code' => ['required', 'digits:6'],
            'purpose' => ['nullable', 'string', 'in:login'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}

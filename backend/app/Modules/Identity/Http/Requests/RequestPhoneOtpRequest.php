<?php

namespace App\Modules\Identity\Http\Requests;

class RequestPhoneOtpRequest extends LoginByPasswordRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{11,15}$/'],
            'purpose' => ['nullable', 'string', 'in:login'],
        ];
    }
}

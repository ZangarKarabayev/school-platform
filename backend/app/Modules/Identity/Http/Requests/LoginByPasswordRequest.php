<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginByPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{11,15}$/'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (! is_string($phone)) {
            return;
        }

        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if ($normalized === '') {
            return;
        }

        if (str_starts_with($normalized, '8') && strlen($normalized) === 11) {
            $normalized = '7'.substr($normalized, 1);
        }

        $this->merge([
            'phone' => '+'.$normalized,
        ]);
    }
}

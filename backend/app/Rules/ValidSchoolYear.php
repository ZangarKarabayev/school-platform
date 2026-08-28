<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSchoolYear implements ValidationRule
{
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^(?<start>\d{4})-(?<end>\d{4})$/', $value, $matches)) {
            return false;
        }

        return (int) $matches['end'] === (int) $matches['start'] + 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('validation.school_year')->translate();
        }
    }
}

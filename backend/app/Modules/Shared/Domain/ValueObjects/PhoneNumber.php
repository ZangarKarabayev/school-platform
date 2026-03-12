<?php

namespace App\Modules\Shared\Domain\ValueObjects;

use InvalidArgumentException;

readonly class PhoneNumber
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^\+?[0-9]{11,15}$/', $value)) {
            throw new InvalidArgumentException('Phone number must be in international format.');
        }
    }
}

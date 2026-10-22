<?php

namespace App\Services\Students;

use Carbon\CarbonImmutable;

class StudentIdentityService
{
    public function extractBirthDateFromIin(?string $iin): ?string
    {
        if (! preg_match('/^\d{12}$/', (string) $iin)) {
            return null;
        }

        $centuryDigit = (int) $iin[6];
        $century = match ($centuryDigit) {
            1, 2 => 1800,
            3, 4 => 1900,
            5, 6 => 2000,
            default => $this->resolveFallbackCenturyFromIin($iin),
        };

        if ($century === null) {
            return null;
        }

        $year = $century + (int) substr($iin, 0, 2);
        $month = (int) substr($iin, 2, 2);
        $day = (int) substr($iin, 4, 2);

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->format('Y-m-d');
    }

    public function extractGenderFromIin(?string $iin): ?string
    {
        if (! preg_match('/^\d{12}$/', (string) $iin)) {
            return null;
        }

        if (! in_array((int) $iin[6], [1, 2, 3, 4, 5, 6], true)) {
            return null;
        }

        return ((int) $iin[6]) % 2 === 1 ? 'male' : 'female';
    }

    private function resolveFallbackCenturyFromIin(string $iin): ?int
    {
        $month = (int) substr($iin, 2, 2);
        $day = (int) substr($iin, 4, 2);
        $minAge = 5;
        $maxAge = 25;

        foreach ([2000, 1900, 1800] as $candidateCentury) {
            $year = $candidateCentury + (int) substr($iin, 0, 2);

            if (! checkdate($month, $day, $year)) {
                continue;
            }

            $birthDate = CarbonImmutable::create($year, $month, $day);
            $age = $birthDate->diffInYears(now(), false);

            if ($age >= $minAge && $age <= $maxAge) {
                return $candidateCentury;
            }
        }

        return null;
    }
}

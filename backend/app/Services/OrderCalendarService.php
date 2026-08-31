<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class OrderCalendarService
{
    /**
     * Official calendars used to prevent orders on non-school days.
     *
     * Each calendar remains active through August 31 so the summer break is
     * blocked until the next school year starts.
     *
     * @var array<string, array{
     *     start: string,
     *     end: string,
     *     breaks: array<int, array{0: string, 1: string}>,
     *     grade_breaks?: array<int, array<int, array{0: string, 1: string}>>,
     *     holidays: array<int, string>
     * }>
     */
    private const SCHOOL_YEARS = [
        '2025-2026' => [
            'start' => '2025-09-01',
            'end' => '2026-08-31',
            'breaks' => [
                ['2025-10-27', '2025-11-02'],
                ['2025-12-29', '2026-01-07'],
                ['2026-03-19', '2026-03-29'],
                ['2026-05-26', '2026-08-31'],
            ],
            'holidays' => [
                '2025-10-25',
                '2025-10-27',
                '2025-12-16',
                '2026-01-01',
                '2026-01-02',
                '2026-01-07',
                '2026-03-08',
                '2026-03-09',
                '2026-03-21',
                '2026-03-22',
                '2026-03-23',
                '2026-03-24',
                '2026-03-25',
                '2026-05-01',
                '2026-05-07',
                '2026-05-09',
                '2026-05-11',
                '2026-05-27',
            ],
        ],
        '2026-2027' => [
            'start' => '2026-09-01',
            'end' => '2027-08-31',
            'breaks' => [
                ['2026-10-26', '2026-11-01'],
                ['2026-12-28', '2027-01-10'],
                ['2027-03-22', '2027-03-28'],
                ['2027-05-26', '2027-08-31'],
            ],
            'grade_breaks' => [
                1 => [
                    ['2027-02-08', '2027-02-14'],
                ],
            ],
            'holidays' => [
                '2026-10-25',
                '2026-10-26',
                '2026-12-16',
                '2027-01-01',
                '2027-01-02',
                '2027-01-04',
                '2027-01-07',
                '2027-03-08',
                '2027-03-15',
                '2027-03-21',
                '2027-03-22',
                '2027-03-23',
                '2027-03-24',
                '2027-05-01',
                '2027-05-03',
                '2027-05-07',
                '2027-05-09',
                '2027-05-10',
            ],
        ],
    ];

    public function isBlockedOrderDate(CarbonInterface|string $date, ?int $grade = null): bool
    {
        $date = $this->normalizeDate($date);
        $calendar = $this->calendarFor($date);

        if ($calendar === null) {
            return false;
        }

        foreach ($calendar['breaks'] as [$startDate, $endDate]) {
            if ($date->betweenIncluded($startDate, $endDate)) {
                return true;
            }
        }

        foreach ($calendar['grade_breaks'][$grade] ?? [] as [$startDate, $endDate]) {
            if ($date->betweenIncluded($startDate, $endDate)) {
                return true;
            }
        }

        return $date->isWeekend()
            || in_array($date->toDateString(), $calendar['holidays'], true);
    }

    public function blockedOrderDateMessage(CarbonInterface|string $date): string
    {
        return sprintf(
            "\u{0417}\u{0430}\u{043A}\u{0430}\u{0437}\u{044B} \u{043D}\u{0435} \u{0441}\u{043E}\u{0437}\u{0434}\u{0430}\u{044E}\u{0442}\u{0441}\u{044F} \u{0432} \u{0432}\u{044B}\u{0445}\u{043E}\u{0434}\u{043D}\u{044B}\u{0435}, \u{043F}\u{0440}\u{0430}\u{0437}\u{0434}\u{043D}\u{0438}\u{0447}\u{043D}\u{044B}\u{0435} \u{0434}\u{043D}\u{0438} \u{0438} \u{0432}\u{043E} \u{0432}\u{0440}\u{0435}\u{043C}\u{044F} \u{0448}\u{043A}\u{043E}\u{043B}\u{044C}\u{043D}\u{044B}\u{0445} \u{043A}\u{0430}\u{043D}\u{0438}\u{043A}\u{0443}\u{043B}. \u{0414}\u{0430}\u{0442}\u{0430}: %s.",
            $this->normalizeDate($date)->format('d.m.Y')
        );
    }

    private function normalizeDate(CarbonInterface|string $date): CarbonInterface
    {
        if ($date instanceof CarbonInterface) {
            return $date->copy()->startOfDay();
        }

        return Carbon::parse($date, config('app.timezone'))->startOfDay();
    }

    /**
     * @return array{
     *     start: string,
     *     end: string,
     *     breaks: array<int, array{0: string, 1: string}>,
     *     grade_breaks?: array<int, array<int, array{0: string, 1: string}>>,
     *     holidays: array<int, string>
     * }|null
     */
    private function calendarFor(CarbonInterface $date): ?array
    {
        foreach (self::SCHOOL_YEARS as $calendar) {
            if ($date->betweenIncluded($calendar['start'], $calendar['end'])) {
                return $calendar;
            }
        }

        return null;
    }
}

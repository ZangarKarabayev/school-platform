<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class OrderCalendarService
{
    private const SCHOOL_YEAR_START = '2025-09-01';

    private const SCHOOL_YEAR_END = '2026-05-31';

    /**
     * Official school breaks for all students in the 2025-2026 school year.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const SCHOOL_BREAKS = [
        ['2025-10-27', '2025-11-02'],
        ['2025-12-29', '2026-01-07'],
        ['2026-03-19', '2026-03-29'],
        ['2026-05-26', '2026-08-31'],
    ];

    /**
     * Official holidays and observed days off in Kazakhstan during 2025-2026 school year.
     *
     * @var array<int, string>
     */
    private const HOLIDAYS = [
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
    ];

    public function isBlockedOrderDate(CarbonInterface|string $date): bool
    {
        $date = $this->normalizeDate($date);

        foreach (self::SCHOOL_BREAKS as [$startDate, $endDate]) {
            if ($date->betweenIncluded($startDate, $endDate)) {
                return true;
            }
        }

        if (! $date->betweenIncluded(self::SCHOOL_YEAR_START, self::SCHOOL_YEAR_END)) {
            return false;
        }

        return $date->isWeekend()
            || in_array($date->toDateString(), self::HOLIDAYS, true);
    }

    public function blockedOrderDateMessage(CarbonInterface|string $date): string
    {
        return sprintf(
            "\u{0417}\u{0430}\u{043A}\u{0430}\u{0437}\u{044B} \u{043D}\u{0435} \u{0441}\u{043E}\u{0437}\u{0434}\u{0430}\u{044E}\u{0442}\u{0441}\u{044F} \u{0432} \u{0432}\u{044B}\u{0445}\u{043E}\u{0434}\u{043D}\u{044B}\u{0435} \u{0438} \u{043F}\u{0440}\u{0430}\u{0437}\u{0434}\u{043D}\u{0438}\u{0447}\u{043D}\u{044B}\u{0435} \u{0434}\u{043D}\u{0438}. \u{0414}\u{0430}\u{0442}\u{0430}: %s.",
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
}

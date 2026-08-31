<?php

namespace Tests\Unit\Services;

use App\Services\OrderCalendarService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderCalendarServiceTest extends TestCase
{
    #[DataProvider('blockedDates')]
    public function test_it_blocks_non_school_dates_for_2026_2027(
        string $date,
        ?int $grade = null,
    ): void {
        $this->assertTrue(app(OrderCalendarService::class)->isBlockedOrderDate($date, $grade));
    }

    public static function blockedDates(): array
    {
        return [
            'weekend' => ['2026-09-05'],
            'autumn break start' => ['2026-10-26'],
            'autumn break end' => ['2026-11-01'],
            'independence day' => ['2026-12-16'],
            'winter break start' => ['2026-12-28'],
            'winter break end' => ['2027-01-10'],
            'first grade extra break' => ['2027-02-08', 1],
            'international womens day' => ['2027-03-08'],
            'constitution day' => ['2027-03-15'],
            'spring break start' => ['2027-03-22'],
            'spring break end' => ['2027-03-28'],
            'unity day observed' => ['2027-05-03'],
            'defender day' => ['2027-05-07'],
            'victory day observed' => ['2027-05-10'],
            'summer break start' => ['2027-05-26'],
        ];
    }

    #[DataProvider('schoolDates')]
    public function test_it_allows_school_dates_for_2026_2027(
        string $date,
        ?int $grade = null,
    ): void {
        $this->assertFalse(app(OrderCalendarService::class)->isBlockedOrderDate($date, $grade));
    }

    public static function schoolDates(): array
    {
        return [
            'first day' => ['2026-09-01'],
            'before autumn break' => ['2026-10-23'],
            'after autumn break' => ['2026-11-02'],
            'extra break does not affect other grades' => ['2027-02-08', 2],
            'after first grade extra break' => ['2027-02-15', 1],
            'before spring break' => ['2027-03-19'],
            'after spring break' => ['2027-03-29'],
            'last school day' => ['2027-05-25'],
        ];
    }

    public function test_it_keeps_the_previous_school_year_calendar_available(): void
    {
        $service = app(OrderCalendarService::class);

        $this->assertTrue($service->isBlockedOrderDate('2026-03-24'));
        $this->assertFalse($service->isBlockedOrderDate('2026-04-22'));
    }
}

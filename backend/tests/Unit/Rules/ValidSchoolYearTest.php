<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidSchoolYear;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidSchoolYearTest extends TestCase
{
    public function test_it_accepts_a_valid_school_year(): void
    {
        $this->assertTrue(ValidSchoolYear::isValid('2025-2026'));
    }

    public function test_it_returns_a_localized_validation_error(): void
    {
        $this->app->setLocale('ru');

        $validator = validator(
            ['school_year' => '2025-2027'],
            ['school_year' => ['required', new ValidSchoolYear]],
        );

        $this->assertSame(
            'Учебный год должен иметь формат YYYY-YYYY, а второй год должен быть на единицу больше первого.',
            $validator->errors()->first('school_year'),
        );
    }

    #[DataProvider('invalidSchoolYears')]
    public function test_it_rejects_invalid_school_years(mixed $value): void
    {
        $this->assertFalse(ValidSchoolYear::isValid($value));
    }

    public static function invalidSchoolYears(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'wrong separator' => ['2025/2026'],
            'non-consecutive years' => ['2025-2027'],
            'reverse years' => ['2026-2025'],
            'short year' => ['25-26'],
        ];
    }
}

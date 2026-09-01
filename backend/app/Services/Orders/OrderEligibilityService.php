<?php

namespace App\Services\Orders;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Rules\ValidSchoolYear;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class OrderEligibilityService
{
    /**
     * @return array{eligible: bool, classroom_id: ?int, grade: ?int}
     */
    public function evaluate(
        Student $student,
        ?string $schoolYear,
        CarbonInterface|string $orderDate,
        ?int $schoolId = null,
    ): array {
        $schoolYear = filled($schoolYear) ? trim((string) $schoolYear) : null;
        $date = $orderDate instanceof CarbonInterface
            ? CarbonImmutable::instance($orderDate)
            : CarbonImmutable::parse($orderDate);

        if ($student->status === 'graduated') {
            return ['eligible' => false, 'classroom_id' => null, 'grade' => null];
        }

        if ($schoolYear === null) {
            return [
                'eligible' => $student->canCreateOrder(),
                'classroom_id' => $student->classroom_id ? (int) $student->classroom_id : null,
                'grade' => $student->classroom?->grade !== null ? (int) $student->classroom->grade : null,
            ];
        }

        if (! ValidSchoolYear::isValid($schoolYear) || ! $this->dateBelongsToSchoolYear($date, $schoolYear)) {
            return ['eligible' => false, 'classroom_id' => null, 'grade' => null];
        }

        $enrollment = $student->relationLoaded('enrollments')
            ? $student->enrollments->firstWhere('school_year', $schoolYear)
            : $student->enrollments()
            ->with('classroom')
            ->where('school_year', $schoolYear)
            ->first();

        if (! $enrollment && $student->classroom_id !== null && (blank($student->school_year) || $student->school_year === $schoolYear)) {
            $enrollment = new StudentEnrollment([
                'school_id' => $student->school_id,
                'classroom_id' => $student->classroom_id,
                'school_year' => $schoolYear,
            ]);
            $enrollment->setRelation('classroom', $student->classroom);
        }

        if (! $enrollment || ! $enrollment->classroom_id || ($schoolId !== null && (int) $enrollment->school_id !== $schoolId)) {
            return ['eligible' => false, 'classroom_id' => null, 'grade' => null];
        }

        if (($enrollment->started_at !== null && $date->lt($enrollment->started_at))
            || ($enrollment->ended_at !== null && $date->gt($enrollment->ended_at))
        ) {
            return ['eligible' => false, 'classroom_id' => null, 'grade' => null];
        }

        $enrollment->loadMissing('classroom');
        $grade = $enrollment->classroom?->grade !== null ? (int) $enrollment->classroom->grade : null;

        if ($grade === null) {
            return ['eligible' => false, 'classroom_id' => (int) $enrollment->classroom_id, 'grade' => null];
        }

        if ($grade >= 1 && $grade <= 4) {
            return ['eligible' => true, 'classroom_id' => (int) $enrollment->classroom_id, 'grade' => $grade];
        }

        $student->loadMissing('latestMealBenefit');
        $benefit = $student->latestMealBenefit;
        $eligibleByBenefit = $benefit !== null
            && in_array($benefit->type, Student::ORDER_ELIGIBLE_BENEFIT_TYPES, true)
            && ($benefit->start_date === null || $benefit->start_date->lte($date))
            && ($benefit->end_date === null || $benefit->end_date->gte($date));

        return [
            'eligible' => $eligibleByBenefit,
            'classroom_id' => (int) $enrollment->classroom_id,
            'grade' => $grade,
        ];
    }

    public function isEligible(
        Student $student,
        ?string $schoolYear,
        CarbonInterface|string $orderDate,
        ?int $schoolId = null,
    ): bool {
        return $this->evaluate($student, $schoolYear, $orderDate, $schoolId)['eligible'];
    }

    public function dateBelongsToSchoolYear(CarbonInterface|string $date, string $schoolYear): bool
    {
        if (! ValidSchoolYear::isValid($schoolYear)) {
            return false;
        }

        $date = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date);
        $startYear = (int) substr($schoolYear, 0, 4);
        $endYear = (int) substr($schoolYear, 5, 4);
        $start = CarbonImmutable::create($startYear, 9, 1)->startOfDay();
        $end = CarbonImmutable::create($endYear, 5, 31)->endOfDay();

        return $date->betweenIncluded($start, $end);
    }
}

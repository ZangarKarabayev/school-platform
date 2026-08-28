<?php

namespace App\Services\Students;

use App\Models\AcademicClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotionBatch;
use App\Modules\Organizations\Models\School;
use App\Rules\ValidSchoolYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class StudentPromotionService
{
    private const GRADES = [11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1];

    /**
     * @return array{
     *     school: School,
     *     grade: int,
     *     from_school_year: string,
     *     to_school_year: string,
     *     total_count: int,
     *     normalized_year_count: int,
     *     groups: list<array{from: string, to: string, count: int}>,
     *     errors: list<string>
     * }
     */
    public function preview(
        int $schoolId,
        int $grade,
        string $fromSchoolYear,
        string $toSchoolYear,
        bool $includeNullSchoolYear = false,
    ): array {
        $this->validateParameters($grade, $fromSchoolYear, $toSchoolYear);

        $school = School::query()->find($schoolId);

        if (! $school) {
            throw new InvalidArgumentException("School {$schoolId} was not found.");
        }

        $students = $this->candidateQuery($schoolId, $grade, $fromSchoolYear, $includeNullSchoolYear)
            ->with('classroom')
            ->orderBy('classroom_id')
            ->orderBy('id')
            ->get();

        $errors = [];
        $targets = $grade === 11
            ? collect()
            : $this->targetClassrooms($grade, $students);

        $groups = $students
            ->groupBy('classroom_id')
            ->map(function (Collection $classStudents) use ($grade, $targets, &$errors): array {
                /** @var Student $firstStudent */
                $firstStudent = $classStudents->first();
                $classroom = $firstStudent->classroom;
                $target = $grade === 11 ? null : $targets->get(mb_strtoupper((string) $classroom?->letter));

                if ($grade !== 11 && ! $target) {
                    $errors[] = sprintf(
                        'Для класса %s не найден следующий класс %d%s.',
                        $classroom?->full_name ?? '#'.$firstStudent->classroom_id,
                        $grade + 1,
                        $classroom?->letter ?? '',
                    );
                }

                return [
                    'from' => $classroom?->full_name ?? '#'.$firstStudent->classroom_id,
                    'to' => $grade === 11 ? 'Выпуск' : ($target?->full_name ?? 'Не найден'),
                    'count' => $classStudents->count(),
                ];
            })
            ->values()
            ->all();

        $conflictingStudentIds = StudentEnrollment::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->where('school_year', $toSchoolYear)
            ->pluck('student_id');

        if ($conflictingStudentIds->isNotEmpty()) {
            $errors[] = sprintf(
                'У %d учеников уже существует зачисление за %s.',
                $conflictingStudentIds->count(),
                $toSchoolYear,
            );
        }

        return [
            'school' => $school,
            'grade' => $grade,
            'from_school_year' => $fromSchoolYear,
            'to_school_year' => $toSchoolYear,
            'total_count' => $students->count(),
            'normalized_year_count' => $students->whereNull('school_year')->count(),
            'groups' => $groups,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @return array{
     *     school: School,
     *     from_school_year: string,
     *     to_school_year: string,
     *     total_count: int,
     *     normalized_year_count: int,
     *     groups: list<array{grade: int, from: string, to: string, count: int}>,
     *     errors: list<string>
     * }
     */
    public function previewAll(
        int $schoolId,
        string $fromSchoolYear,
        string $toSchoolYear,
        bool $includeNullSchoolYear = false,
    ): array {
        $previews = collect(self::GRADES)
            ->mapWithKeys(fn (int $grade): array => [
                $grade => $this->preview(
                    $schoolId,
                    $grade,
                    $fromSchoolYear,
                    $toSchoolYear,
                    $includeNullSchoolYear,
                ),
            ]);
        $firstPreview = $previews->first();

        return [
            'school' => $firstPreview['school'],
            'from_school_year' => $fromSchoolYear,
            'to_school_year' => $toSchoolYear,
            'total_count' => $previews->sum('total_count'),
            'normalized_year_count' => $previews->sum('normalized_year_count'),
            'groups' => $previews
                ->flatMap(fn (array $preview, int $grade): array => array_map(
                    fn (array $group): array => ['grade' => $grade] + $group,
                    $preview['groups'],
                ))
                ->values()
                ->all(),
            'errors' => $previews
                ->flatMap(fn (array $preview, int $grade): array => array_map(
                    fn (string $error): string => "Grade {$grade}: {$error}",
                    $preview['errors'],
                ))
                ->unique()
                ->values()
                ->all(),
        ];
    }

    public function promote(
        int $schoolId,
        int $grade,
        string $fromSchoolYear,
        string $toSchoolYear,
        ?int $createdBy = null,
        bool $includeNullSchoolYear = false,
    ): StudentPromotionBatch {
        $preview = $this->preview(
            $schoolId,
            $grade,
            $fromSchoolYear,
            $toSchoolYear,
            $includeNullSchoolYear,
        );

        if ($preview['errors'] !== []) {
            throw new RuntimeException(implode(PHP_EOL, $preview['errors']));
        }

        $batch = StudentPromotionBatch::query()->create([
            'school_id' => $schoolId,
            'created_by' => $createdBy,
            'grade' => $grade,
            'from_school_year' => $fromSchoolYear,
            'to_school_year' => $toSchoolYear,
            'status' => 'processing',
            'total_count' => $preview['total_count'],
            'normalized_year_count' => $preview['normalized_year_count'],
            'started_at' => now(),
        ]);

        try {
            DB::transaction(function () use ($batch, $schoolId, $grade, $fromSchoolYear, $toSchoolYear, $includeNullSchoolYear): void {
                $students = $this->candidateQuery($schoolId, $grade, $fromSchoolYear, $includeNullSchoolYear)
                    ->with('classroom')
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                $targets = $grade === 11
                    ? collect()
                    : $this->targetClassrooms($grade, $students);
                $promotedCount = 0;
                $graduatedCount = 0;
                $normalizedYearCount = 0;
                $transitionDate = now()->toDateString();

                foreach ($students as $student) {
                    $oldClassroom = $student->classroom;
                    $oldSchoolYear = $student->school_year;
                    $oldStatus = $student->status;

                    if ($oldSchoolYear === null) {
                        $normalizedYearCount++;
                    }

                    $oldEnrollment = StudentEnrollment::query()->firstOrNew([
                        'student_id' => $student->id,
                        'school_year' => $fromSchoolYear,
                    ]);
                    $oldEnrollment->fill([
                        'school_id' => $schoolId,
                        'classroom_id' => $student->classroom_id,
                        'status' => $grade === 11
                            ? StudentEnrollment::STATUS_GRADUATED
                            : StudentEnrollment::STATUS_COMPLETED,
                        'ended_at' => $transitionDate,
                    ]);
                    $oldEnrollment->save();

                    if ($grade === 11) {
                        $student->fill([
                            'school_year' => $fromSchoolYear,
                            'classroom_id' => null,
                            'status' => 'graduated',
                        ])->save();

                        $batch->items()->create([
                            'student_id' => $student->id,
                            'old_classroom_id' => $oldClassroom?->id,
                            'new_classroom_id' => null,
                            'old_school_year' => $oldSchoolYear,
                            'new_school_year' => $fromSchoolYear,
                            'old_status' => $oldStatus,
                            'new_status' => 'graduated',
                            'result' => 'graduated',
                        ]);

                        $graduatedCount++;

                        continue;
                    }

                    $target = $targets->get(mb_strtoupper((string) $oldClassroom?->letter));

                    if (! $target) {
                        throw new RuntimeException("Target classroom for student {$student->id} was not found.");
                    }

                    StudentEnrollment::query()->create([
                        'student_id' => $student->id,
                        'school_id' => $schoolId,
                        'classroom_id' => $target->id,
                        'school_year' => $toSchoolYear,
                        'status' => StudentEnrollment::STATUS_CURRENT,
                        'started_at' => $transitionDate,
                    ]);

                    $student->fill([
                        'classroom_id' => $target->id,
                        'school_year' => $toSchoolYear,
                    ])->save();

                    $batch->items()->create([
                        'student_id' => $student->id,
                        'old_classroom_id' => $oldClassroom?->id,
                        'new_classroom_id' => $target->id,
                        'old_school_year' => $oldSchoolYear,
                        'new_school_year' => $toSchoolYear,
                        'old_status' => $oldStatus,
                        'new_status' => $student->status,
                        'result' => 'promoted',
                    ]);

                    $promotedCount++;
                }

                $batch->forceFill([
                    'status' => 'completed',
                    'total_count' => $students->count(),
                    'promoted_count' => $promotedCount,
                    'graduated_count' => $graduatedCount,
                    'normalized_year_count' => $normalizedYearCount,
                    'completed_at' => now(),
                ])->save();
            }, 3);
        } catch (Throwable $exception) {
            $batch->forceFill([
                'status' => 'failed',
                'errors' => [$exception->getMessage()],
                'completed_at' => now(),
            ])->save();

            throw $exception;
        }

        return $batch->fresh('items');
    }

    /** @return Collection<int, StudentPromotionBatch> */
    public function promoteAll(
        int $schoolId,
        string $fromSchoolYear,
        string $toSchoolYear,
        ?int $createdBy = null,
        bool $includeNullSchoolYear = false,
    ): Collection {
        $preview = $this->previewAll(
            $schoolId,
            $fromSchoolYear,
            $toSchoolYear,
            $includeNullSchoolYear,
        );

        if ($preview['errors'] !== []) {
            throw new RuntimeException(implode(PHP_EOL, $preview['errors']));
        }

        return DB::transaction(function () use (
            $schoolId,
            $fromSchoolYear,
            $toSchoolYear,
            $createdBy,
            $includeNullSchoolYear,
        ): Collection {
            return collect(self::GRADES)
                ->map(fn (int $grade): StudentPromotionBatch => $this->promote(
                    $schoolId,
                    $grade,
                    $fromSchoolYear,
                    $toSchoolYear,
                    $createdBy,
                    $includeNullSchoolYear,
                ));
        }, 3);
    }

    private function candidateQuery(
        int $schoolId,
        int $grade,
        string $fromSchoolYear,
        bool $includeNullSchoolYear,
    ): Builder {
        return Student::query()
            ->where('school_id', $schoolId)
            ->whereHas('classroom', fn (Builder $query) => $query->where('grade', $grade))
            ->where(function (Builder $query) use ($fromSchoolYear, $includeNullSchoolYear): void {
                $query->where('school_year', $fromSchoolYear);

                if ($includeNullSchoolYear) {
                    $query->orWhereNull('school_year');
                }
            });
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<string, AcademicClass>
     */
    private function targetClassrooms(int $grade, Collection $students): Collection
    {
        $letters = $students
            ->pluck('classroom.letter')
            ->filter()
            ->map(fn (string $letter): string => mb_strtoupper(trim($letter)))
            ->unique()
            ->values();

        return AcademicClass::query()
            ->where('grade', $grade + 1)
            ->whereIn('letter', $letters)
            ->get()
            ->keyBy(fn (AcademicClass $classroom): string => mb_strtoupper($classroom->letter));
    }

    private function validateParameters(int $grade, string $fromSchoolYear, string $toSchoolYear): void
    {
        if ($grade < 1 || $grade > 11) {
            throw new InvalidArgumentException('Grade must be between 1 and 11.');
        }

        if (! ValidSchoolYear::isValid($fromSchoolYear) || ! ValidSchoolYear::isValid($toSchoolYear)) {
            throw new InvalidArgumentException('School years must have the YYYY-YYYY format with consecutive years.');
        }

        if (substr($fromSchoolYear, 5, 4) !== substr($toSchoolYear, 0, 4)) {
            throw new InvalidArgumentException('The target school year must immediately follow the source school year.');
        }
    }
}

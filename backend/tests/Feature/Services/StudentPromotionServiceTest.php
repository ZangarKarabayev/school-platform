<?php

namespace Tests\Feature\Services;

use App\Models\AcademicClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotionBatch;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use App\Services\Students\StudentPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_promotes_only_students_from_the_selected_grade_and_school_year(): void
    {
        $school = $this->makeSchool();
        $gradeOne = $this->makeClassroom(1, 'А');
        $gradeTwo = $this->makeClassroom(2, 'А');
        $gradeThree = $this->makeClassroom(3, 'А');

        $activeStudent = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeOne->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);
        $activeStudentWithNullYear = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeOne->id,
            'school_year' => null,
            'status' => 'active',
        ]);
        $existingSecondGrader = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeTwo->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);

        $batch = app(StudentPromotionService::class)->promote(
            $school->id,
            1,
            '2025-2026',
            '2026-2027',
        );

        $this->assertSame(1, $batch->promoted_count);
        $this->assertSame(0, $batch->normalized_year_count);
        $this->assertSame('active', $activeStudent->fresh()->status);
        $this->assertSame($gradeTwo->id, $activeStudent->fresh()->classroom_id);
        $this->assertSame('2026-2027', $activeStudent->fresh()->school_year);
        $this->assertSame([$gradeOne->id, $gradeTwo->id], $activeStudent->fresh()->classroom_history);
        $this->assertSame($gradeOne->id, $activeStudentWithNullYear->fresh()->classroom_id);
        $this->assertSame($gradeTwo->id, $existingSecondGrader->fresh()->classroom_id);
        $this->assertSame('2025-2026', $existingSecondGrader->fresh()->school_year);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $activeStudent->id,
            'classroom_id' => $gradeOne->id,
            'school_year' => '2025-2026',
            'status' => StudentEnrollment::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $activeStudent->id,
            'classroom_id' => $gradeTwo->id,
            'school_year' => '2026-2027',
            'status' => StudentEnrollment::STATUS_CURRENT,
        ]);
        $this->assertDatabaseMissing('student_enrollments', [
            'student_id' => $activeStudent->id,
            'classroom_id' => $gradeThree->id,
        ]);
    }

    public function test_null_school_year_requires_explicit_opt_in(): void
    {
        $school = $this->makeSchool();
        $gradeOne = $this->makeClassroom(1, 'N');
        $gradeTwo = $this->makeClassroom(2, 'N');
        $activeStudent = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeOne->id,
            'school_year' => null,
            'status' => 'active',
        ]);
        $batch = app(StudentPromotionService::class)->promote(
            $school->id,
            1,
            '2025-2026',
            '2026-2027',
            null,
            true,
        );

        $this->assertSame(1, $batch->promoted_count);
        $this->assertSame(1, $batch->normalized_year_count);
        $this->assertSame($gradeTwo->id, $activeStudent->fresh()->classroom_id);
    }

    public function test_missing_target_classroom_prevents_the_entire_promotion(): void
    {
        $school = $this->makeSchool();
        $gradeOneA = $this->makeClassroom(1, 'A');
        $gradeOneB = $this->makeClassroom(1, 'B');
        $this->makeClassroom(2, 'A');
        $studentA = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeOneA->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);
        $studentB = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeOneB->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);

        try {
            app(StudentPromotionService::class)->promote(
                $school->id,
                1,
                '2025-2026',
                '2026-2027',
            );
            $this->fail('Promotion should fail when one target classroom is missing.');
        } catch (\RuntimeException) {
            $this->assertSame($gradeOneA->id, $studentA->fresh()->classroom_id);
            $this->assertSame($gradeOneB->id, $studentB->fresh()->classroom_id);
            $this->assertDatabaseMissing('student_enrollments', [
                'student_id' => $studentA->id,
                'school_year' => '2026-2027',
            ]);
            $this->assertDatabaseCount('student_promotion_batches', 0);
        }
    }

    public function test_it_graduates_the_eleventh_grade_without_creating_a_new_enrollment(): void
    {
        $school = $this->makeSchool();
        $gradeEleven = $this->makeClassroom(11, 'Б');
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeEleven->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);

        $batch = app(StudentPromotionService::class)->promote(
            $school->id,
            11,
            '2025-2026',
            '2026-2027',
        );

        $student->refresh();

        $this->assertSame(1, $batch->graduated_count);
        $this->assertSame('graduated', $student->status);
        $this->assertNull($student->classroom_id);
        $this->assertSame('2025-2026', $student->school_year);
        $this->assertSame([$gradeEleven->id], $student->classroom_history);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'school_year' => '2025-2026',
            'status' => StudentEnrollment::STATUS_GRADUATED,
        ]);
        $this->assertDatabaseMissing('student_enrollments', [
            'student_id' => $student->id,
            'school_year' => '2026-2027',
        ]);
    }

    public function test_dry_run_command_changes_nothing_and_real_command_is_idempotent(): void
    {
        $school = $this->makeSchool();
        $gradeOne = $this->makeClassroom(1, 'В');
        $gradeTwo = $this->makeClassroom(2, 'В');
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeOne->id,
            'school_year' => '2025-2026',
        ]);
        $arguments = [
            '--school' => $school->id,
            '--grade' => 1,
            '--from' => '2025-2026',
            '--to' => '2026-2027',
        ];

        $this->artisan('students:promote', $arguments + ['--dry-run' => true])
            ->assertSuccessful();
        $this->assertSame($gradeOne->id, $student->fresh()->classroom_id);
        $this->assertDatabaseCount('student_promotion_batches', 0);

        $this->artisan('students:promote', $arguments + ['--force' => true])
            ->assertSuccessful();
        $this->assertSame($gradeTwo->id, $student->fresh()->classroom_id);
        $this->assertDatabaseCount('student_promotion_batches', 1);

        $this->artisan('students:promote', $arguments + ['--force' => true])
            ->assertSuccessful();
        $this->assertDatabaseCount('student_promotion_batches', 2);
        $this->assertSame(0, StudentPromotionBatch::query()->latest('id')->value('total_count'));
    }

    public function test_all_grades_command_promotes_top_down_without_promoting_any_student_twice(): void
    {
        $school = $this->makeSchool();
        $gradeOne = $this->makeClassroom(1, 'Z');
        $gradeTwo = $this->makeClassroom(2, 'Z');
        $gradeThree = $this->makeClassroom(3, 'Z');
        $gradeEleven = $this->makeClassroom(11, 'Y');
        $firstGrader = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeOne->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);
        $secondGrader = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeTwo->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);
        $eleventhGrader = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeEleven->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);

        $this->artisan('students:promote', [
            '--school' => $school->id,
            '--all-grades' => true,
            '--from' => '2025-2026',
            '--to' => '2026-2027',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame($gradeTwo->id, $firstGrader->fresh()->classroom_id);
        $this->assertSame($gradeThree->id, $secondGrader->fresh()->classroom_id);
        $this->assertNull($eleventhGrader->fresh()->classroom_id);
        $this->assertSame('graduated', $eleventhGrader->fresh()->status);
        $this->assertDatabaseCount('student_promotion_batches', 11);
        $this->assertDatabaseCount('student_promotion_items', 3);
    }

    public function test_all_grades_command_changes_nothing_when_any_target_classroom_is_missing(): void
    {
        $school = $this->makeSchool();
        $gradeOne = $this->makeClassroom(1, 'Q');
        $gradeTwo = $this->makeClassroom(2, 'Q');
        $gradeFive = $this->makeClassroom(5, 'X');
        $firstGrader = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeOne->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);
        $fifthGrader = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $gradeFive->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);

        $this->artisan('students:promote', [
            '--school' => $school->id,
            '--all-grades' => true,
            '--from' => '2025-2026',
            '--to' => '2026-2027',
            '--force' => true,
        ])->assertFailed();

        $this->assertSame($gradeOne->id, $firstGrader->fresh()->classroom_id);
        $this->assertSame($gradeFive->id, $fifthGrader->fresh()->classroom_id);
        $this->assertNotSame($gradeTwo->id, $firstGrader->fresh()->classroom_id);
        $this->assertDatabaseCount('student_promotion_batches', 0);
    }

    private function makeClassroom(int $grade, string $letter): AcademicClass
    {
        return AcademicClass::query()->create(compact('grade', 'letter'));
    }

    private function makeSchool(): School
    {
        $region = Region::query()->create([
            'name' => 'Region',
            'name_ru' => 'Region',
            'name_kk' => 'Region',
            'code' => 'promotion-region',
        ]);
        $district = District::query()->create([
            'region_id' => $region->id,
            'name' => 'District',
            'name_ru' => 'District',
            'name_kk' => 'District',
            'code' => 'promotion-district',
        ]);

        return School::query()->create([
            'district_id' => $district->id,
            'name' => 'School',
            'name_ru' => 'School',
            'name_kk' => 'School',
            'code' => 'promotion-school',
            'bin' => '990000000001',
            'address' => 'Address',
            'is_active' => true,
        ]);
    }
}

<?php

namespace Tests\Feature\Services;

use App\Models\AcademicClass;
use App\Models\MealBenefit;
use App\Models\Student;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use App\Services\Orders\OrderEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_grade_is_eligible_without_a_benefit(): void
    {
        $school = $this->makeSchool();
        $classroom = AcademicClass::query()->create(['grade' => 1, 'letter' => 'А']);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'school_year' => '2026-2027',
            'status' => 'active',
        ]);

        $service = app(OrderEligibilityService::class);

        $this->assertTrue($service->isEligible(
            $student,
            '2026-2027',
            '2026-09-10',
            $school->id,
        ));

        $this->assertFalse($service->isEligible($student, '2025-2026', '2026-09-10', $school->id));
        $this->assertFalse($service->isEligible($student, '2026-2027', '2027-06-01', $school->id));
    }

    public function test_upper_grade_requires_a_current_eligible_benefit(): void
    {
        $school = $this->makeSchool();
        $classroom = AcademicClass::query()->create(['grade' => 5, 'letter' => 'А']);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'school_year' => '2026-2027',
            'status' => 'active',
        ]);
        $service = app(OrderEligibilityService::class);

        $this->assertFalse($service->isEligible($student, '2026-2027', '2026-09-10', $school->id));

        MealBenefit::query()->create([
            'student_id' => $student->id,
            'type' => 'voucher',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
        $student->unsetRelation('latestMealBenefit');

        $this->assertTrue($service->isEligible($student, '2026-2027', '2026-09-10', $school->id));
        $this->assertFalse($service->isEligible($student, '2026-2027', '2026-10-01', $school->id));
        $this->assertFalse($service->isEligible($student, '2025-2026', '2026-09-10', $school->id));
    }

    public function test_enrollment_dates_limit_historical_order_eligibility(): void
    {
        $school = $this->makeSchool();
        $classroom = AcademicClass::query()->create(['grade' => 1, 'letter' => 'D']);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);
        $student->enrollments()->where('school_year', '2025-2026')->update([
            'started_at' => '2025-09-05',
            'ended_at' => '2026-05-25',
            'status' => 'completed',
        ]);
        $student->load('enrollments.classroom');
        $service = app(OrderEligibilityService::class);

        $this->assertFalse($service->isEligible($student, '2025-2026', '2025-09-04', $school->id));
        $this->assertTrue($service->isEligible($student, '2025-2026', '2025-09-05', $school->id));
        $this->assertFalse($service->isEligible($student, '2025-2026', '2026-05-26', $school->id));
    }

    public function test_graduated_student_is_never_eligible_for_a_new_order(): void
    {
        $school = $this->makeSchool();
        $classroom = AcademicClass::query()->create(['grade' => 4, 'letter' => 'G']);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'school_year' => '2025-2026',
            'status' => 'graduated',
        ]);

        $this->assertFalse(app(OrderEligibilityService::class)->isEligible(
            $student,
            '2025-2026',
            '2026-05-20',
            $school->id,
        ));
    }

    private function makeSchool(): School
    {
        $region = Region::query()->create([
            'name' => 'Region',
            'name_ru' => 'Region',
            'name_kk' => 'Region',
            'code' => 'eligibility-region',
        ]);
        $district = District::query()->create([
            'region_id' => $region->id,
            'name' => 'District',
            'name_ru' => 'District',
            'name_kk' => 'District',
            'code' => 'eligibility-district',
        ]);

        return School::query()->create([
            'district_id' => $district->id,
            'name' => 'School',
            'name_ru' => 'School',
            'name_kk' => 'School',
            'code' => 'eligibility-school',
            'bin' => '990000000002',
            'address' => 'Address',
            'is_active' => true,
        ]);
    }
}

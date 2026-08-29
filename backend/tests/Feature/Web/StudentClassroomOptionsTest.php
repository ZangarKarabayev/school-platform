<?php

namespace Tests\Feature\Web;

use App\Models\AcademicClass;
use App\Models\Student;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentClassroomOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_select_every_classroom_when_creating_or_editing_a_student(): void
    {
        $region = Region::query()->create([
            'name_ru' => 'Регион',
            'code' => 'region',
        ]);
        $district = District::query()->create([
            'region_id' => $region->id,
            'name_ru' => 'Район',
            'code' => 'district',
        ]);
        $school = School::query()->create([
            'district_id' => $district->id,
            'name_ru' => 'Школа',
            'code' => 'school',
        ]);
        $teacherRole = Role::query()->create([
            'code' => 'teacher',
            'name' => 'Teacher',
            'is_system' => true,
        ]);
        $teacher = User::factory()->create(['school_id' => $school->id]);
        $teacher->roles()->attach($teacherRole->id);

        $usedClassroom = AcademicClass::query()->create(['grade' => 1, 'letter' => 'А']);
        $unusedClassroom = AcademicClass::query()->create(['grade' => 11, 'letter' => 'Я']);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $usedClassroom->id,
            'school_year' => '2026-2027',
        ]);

        $this->actingAs($teacher)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('value="'.$unusedClassroom->id.'"', false)
            ->assertSee($unusedClassroom->full_name);

        $this->actingAs($teacher)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('value="'.$unusedClassroom->id.'"', false)
            ->assertSee($unusedClassroom->full_name);
    }
}

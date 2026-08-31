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

    public function test_teacher_can_search_positive_grade_classrooms_when_creating_or_editing_a_student(): void
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
        $zeroClassroom = AcademicClass::query()->create(['grade' => 0, 'letter' => 'Б']);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $usedClassroom->id,
            'school_year' => '2026-2027',
        ]);

        $createResponse = $this->actingAs($teacher)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('data-classroom-combobox', false)
            ->assertSee('data-classroom-id="'.$unusedClassroom->id.'"', false)
            ->assertSee($unusedClassroom->full_name)
            ->assertSee('value="2026-2027"', false)
            ->assertDontSee('data-classroom-id="'.$zeroClassroom->id.'"', false);

        preg_match('/<div id="create_classroom_options".*?<\/div>/s', $createResponse->getContent(), $createOptions);

        $this->assertNotEmpty($createOptions);
        $this->assertStringNotContainsString('data-classroom-id="'.$zeroClassroom->id.'"', $createOptions[0]);

        $editResponse = $this->actingAs($teacher)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('data-classroom-combobox', false)
            ->assertSee('data-classroom-id="'.$unusedClassroom->id.'"', false)
            ->assertSee($unusedClassroom->full_name);

        preg_match('/<div id="classroom_options".*?<\/div>/s', $editResponse->getContent(), $editOptions);

        $this->assertNotEmpty($editOptions);
        $this->assertStringNotContainsString('data-classroom-id="'.$zeroClassroom->id.'"', $editOptions[0]);
    }

    public function test_zero_grade_classroom_cannot_be_assigned_to_a_student(): void
    {
        $zeroClassroom = AcademicClass::query()->create(['grade' => 0, 'letter' => 'А']);
        $positiveGradeClassroom = AcademicClass::query()->create(['grade' => 1, 'letter' => 'А']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('students.store'), [
                'classroom_id' => $zeroClassroom->id,
                'school_year' => '2026-2027',
            ])
            ->assertSessionHasErrors('classroom_id');

        $this->assertDatabaseCount('students', 0);

        $student = Student::query()->create([
            'classroom_id' => $positiveGradeClassroom->id,
            'school_year' => '2026-2027',
        ]);

        $this->actingAs($user)
            ->put(route('students.update', $student), [
                'classroom_id' => $zeroClassroom->id,
                'school_year' => '2026-2027',
            ])
            ->assertSessionHasErrors('classroom_id');

        $this->assertSame($positiveGradeClassroom->id, $student->refresh()->classroom_id);
    }
}

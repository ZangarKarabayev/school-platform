<?php

namespace Tests\Feature\Web;

use App\Models\Student;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_students_can_be_deleted_in_bulk(): void
    {
        $user = User::factory()->create();
        $firstStudent = Student::query()->create(['first_name' => 'First']);
        $secondStudent = Student::query()->create(['first_name' => 'Second']);
        $untouchedStudent = Student::query()->create(['first_name' => 'Untouched']);

        $this->actingAs($user)
            ->post(route('students.bulk-destroy'), [
                'student_ids' => [$firstStudent->id, $secondStudent->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('student_status', __('ui.students.bulk_deleted', ['count' => 2]));

        $this->assertDatabaseMissing('students', ['id' => $firstStudent->id]);
        $this->assertDatabaseMissing('students', ['id' => $secondStudent->id]);
        $this->assertDatabaseHas('students', ['id' => $untouchedStudent->id]);
    }

    public function test_global_admin_with_a_school_binding_can_delete_students_from_other_schools(): void
    {
        $region = Region::query()->create(['name_ru' => 'Region', 'code' => 'region']);
        $district = District::query()->create([
            'region_id' => $region->id,
            'name_ru' => 'District',
            'code' => 'district',
        ]);
        $firstSchool = School::query()->create([
            'district_id' => $district->id,
            'name_ru' => 'First school',
            'code' => 'first-school',
        ]);
        $secondSchool = School::query()->create([
            'district_id' => $district->id,
            'name_ru' => 'Second school',
            'code' => 'second-school',
        ]);
        $adminRole = Role::query()->create([
            'code' => 'super_admin',
            'name' => 'Super admin',
            'is_system' => true,
        ]);
        $admin = User::factory()->create(['school_id' => $firstSchool->id]);
        $admin->roles()->attach($adminRole);
        $student = Student::query()->create([
            'school_id' => $secondSchool->id,
            'first_name' => 'Student',
        ]);

        $this->actingAs($admin)
            ->post(route('students.bulk-destroy'), ['student_ids' => [$student->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }
}

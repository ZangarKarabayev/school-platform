<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Identity\Application\Actions\IssueApiTokenAction;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentListTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $name): School
    {
        $region = Region::query()->firstOrCreate(['code' => 'test-region'], ['name' => 'Region']);
        $district = District::query()->firstOrCreate(['code' => 'test-district'], ['name' => 'District', 'region_id' => $region->id]);

        return School::query()->create(['name' => $name, 'code' => $name, 'district_id' => $district->id]);
    }

    private function authorizeUser(User $user, bool $grantPermission = true): void
    {
        if ($grantPermission) {
            $role = Role::query()->firstOrCreate(['code' => 'student_manager'], ['name' => 'Student manager']);
            $permission = Permission::query()->firstOrCreate(['code' => 'students.manage'], ['name' => 'Manage students', 'group' => 'students']);
            $role->permissions()->sync([$permission->id]);
            $user->roles()->sync([$role->id]);
        }

        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);
    }

    public function test_school_assignment_without_permission_does_not_grant_access(): void
    {
        $school = $this->school('School');
        $user = User::factory()->create(['school_id' => $school->id]);
        $this->authorizeUser($user, false);
        $this->getJson('/api/mobile/v1/students')->assertForbidden()->assertJsonMissingPath('students');
    }

    public function test_permission_without_school_does_not_grant_global_access(): void
    {
        $this->authorizeUser(User::factory()->create(['school_id' => null]));
        $this->getJson('/api/mobile/v1/students')->assertForbidden()->assertJsonMissingPath('students');
    }

    public function test_list_and_search_cannot_escape_assigned_school(): void
    {
        $school = $this->school('Own school');
        $otherSchool = $this->school('Other school');
        $own = Student::query()->create(['school_id' => $school->id, 'last_name' => 'Shared']);
        Student::query()->create(['school_id' => $otherSchool->id, 'last_name' => 'Shared']);
        Student::query()->create(['school_id' => null, 'last_name' => 'Shared']);
        $this->authorizeUser(User::factory()->create(['school_id' => $school->id]));

        $this->getJson('/api/mobile/v1/students?search=Shared&school_id='.$otherSchool->id)
            ->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('students.0.id', $own->id);
    }

    public function test_school_scope_is_used_when_profile_has_no_school(): void
    {
        $school = $this->school('Scoped school');
        $own = Student::query()->create(['school_id' => $school->id]);
        Student::query()->create(['school_id' => null]);
        $user = User::factory()->create(['school_id' => null]);
        $user->scopes()->create(['scope_type' => 'school', 'scope_id' => $school->id]);
        $this->authorizeUser($user);

        $this->getJson('/api/mobile/v1/students')
            ->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('students.0.id', $own->id);
    }

    public function test_guests_cannot_list_students(): void
    {
        $this->getJson('/api/mobile/v1/students')->assertUnauthorized();
    }
}

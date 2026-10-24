<?php

namespace Tests\Feature\Api;

use App\Models\AcademicClass;
use App\Models\Student;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Identity\Application\Actions\IssueApiTokenAction;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassListTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $code): School
    {
        $region = Region::query()->firstOrCreate(['code' => 'test-region'], ['name' => 'Region']);
        $district = District::query()->firstOrCreate(['code' => 'test-district'], ['name' => 'District', 'region_id' => $region->id]);

        return School::query()->create(['code' => $code, 'name' => $code, 'district_id' => $district->id]);
    }

    private function authorizeUser(User $user): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user->roles()->attach(Role::query()->where('code', 'teacher')->firstOrFail());
        app('auth')->forgetGuards();
        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);
    }

    public function test_class_list_is_limited_to_school_and_counts_only_its_students(): void
    {
        $school = $this->school('Own');
        $otherSchool = $this->school('Other');
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $nextClass = AcademicClass::query()->create(['grade' => 4, 'letter' => 'B']);
        $emptyClass = AcademicClass::query()->create(['grade' => 5, 'letter' => 'C']);
        Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id]);
        Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id]);
        Student::query()->create(['school_id' => $otherSchool->id, 'classroom_id' => $class->id]);
        Student::query()->create(['school_id' => $school->id, 'classroom_id' => $nextClass->id]);
        Student::query()->create(['school_id' => $otherSchool->id, 'classroom_id' => $emptyClass->id]);

        $this->authorizeUser(User::factory()->create(['school_id' => $school->id]));

        $response = $this->getJson('/api/mobile/v1/classes')
            ->assertOk()
            ->assertJsonCount(2, 'classes')
            ->assertJsonPath('classes.0.id', $class->id)
            ->assertJsonPath('classes.0.students_count', 2)
            ->assertJsonPath('classes.1.id', $nextClass->id)
            ->assertJsonPath('classes.1.students_count', 1);

        $this->assertSame(
            ['id', 'grade', 'letter', 'full_name', 'students_count'],
            array_keys($response->json('classes.0')),
        );
    }

    public function test_assigned_teacher_can_list_classes(): void
    {
        $school = $this->school('Own');
        $this->authorizeUser(User::factory()->create(['school_id' => $school->id]));
        $this->getJson('/api/mobile/v1/classes')->assertOk();
    }

    public function test_classes_requires_an_assigned_school(): void
    {
        $unassignedUser = User::factory()->create(['school_id' => null]);
        $this->authorizeUser($unassignedUser);
        $this->getJson('/api/mobile/v1/classes')->assertForbidden();
    }

    public function test_classroom_reference_endpoint_remains_a_shared_catalog(): void
    {
        $school = $this->school('Own');
        $usedClass = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $unusedClass = AcademicClass::query()->create(['grade' => 8, 'letter' => 'B']);
        Student::query()->create(['school_id' => $school->id, 'classroom_id' => $usedClass->id]);
        $this->authorizeUser(User::factory()->create(['school_id' => $school->id]));

        $this->getJson('/api/mobile/v1/classrooms')
            ->assertOk()
            ->assertJsonCount(2, 'classrooms')
            ->assertJsonPath('classrooms.1.id', $unusedClass->id);
    }

    public function test_guests_cannot_list_classes(): void
    {
        $this->getJson('/api/mobile/v1/classes')->assertUnauthorized();
    }
}

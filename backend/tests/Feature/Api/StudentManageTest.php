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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManageTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $code): School
    {
        $region = Region::query()->firstOrCreate(['code' => 'r'], ['name' => 'Region']);
        $district = District::query()->firstOrCreate(['code' => 'd'], ['name' => 'District', 'region_id' => $region->id]);

        return School::query()->create(['code' => $code, 'name' => $code, 'district_id' => $district->id]);
    }

    private function login(?School $school, string $role = 'teacher', bool $scope = false): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['school_id' => $scope ? null : $school?->id]);
        $user->roles()->attach(Role::query()->where('code', $role)->firstOrFail());
        if ($scope && $school) {
            $user->scopes()->create(['scope_type' => 'school', 'scope_id' => $school->id]);
        }
        app('auth')->forgetGuards();
        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);

        return $user;
    }

    public function test_teacher_can_create_read_update_and_delete_in_assigned_school(): void
    {
        Storage::fake('public');
        $school = $this->school('Own');
        $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $response = $this->postJson('/api/mobile/v1/students', [
            'first_name' => 'Dana', 'last_name' => 'Student',
            'classroom_id' => $class->id, 'school_year' => '2026-2027',
        ])->assertCreated()->assertJsonPath('student.first_name', 'Dana')
            ->assertJsonPath('student.iin', null)->assertJsonPath('student.meal_benefit_type', null);
        $id = $response->json('student.id');
        $this->assertDatabaseHas('students', ['id' => $id, 'school_id' => $school->id]);
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $id, 'classroom_id' => $class->id]);
        $this->getJson('/api/mobile/v1/students/'.$id)->assertOk()->assertJsonPath('student.last_name', 'Student');
        $this->patchJson('/api/mobile/v1/students/'.$id, ['first_name' => 'New'])
            ->assertOk()->assertJsonPath('student.first_name', 'New')->assertJsonPath('student.last_name', 'Student');
        $this->putJson('/api/mobile/v1/students/'.$id, ['middle_name' => 'Middle'])->assertOk();
        $student = Student::findOrFail($id);
        Storage::disk('public')->put('user/photos/test.jpg', 'photo');
        $student->update(['photo' => 'user/photos/test.jpg']);
        $this->deleteJson('/api/mobile/v1/students/'.$id)->assertNoContent();
        $this->assertSoftDeleted('students', ['id' => $id]);
        Storage::disk('public')->assertExists('user/photos/test.jpg');
    }

    public function test_foreign_and_unassigned_students_cannot_be_read_changed_or_deleted(): void
    {
        $school = $this->school('Own');
        $other = $this->school('Other');
        $this->login($school);
        foreach ([$other->id, null] as $schoolId) {
            $student = Student::query()->create(['school_id' => $schoolId, 'first_name' => 'Protected']);
            $this->getJson('/api/mobile/v1/students/'.$student->id)->assertNotFound();
            $this->patchJson('/api/mobile/v1/students/'.$student->id, ['first_name' => 'Changed'])->assertNotFound();
            $this->putJson('/api/mobile/v1/students/'.$student->id, ['first_name' => 'Changed'])->assertNotFound();
            $this->deleteJson('/api/mobile/v1/students/'.$student->id)->assertNotFound();
            $this->assertDatabaseHas('students', ['id' => $student->id, 'first_name' => 'Protected', 'school_id' => $schoolId]);
        }
    }

    public function test_teacher_without_school_and_non_teacher_are_forbidden(): void
    {
        $school = $this->school('Own');
        $student = Student::query()->create(['school_id' => $school->id]);
        foreach ([[null, 'teacher'], [$school, 'super_admin'], [$school, 'director']] as [$assignment, $role]) {
            $this->login($assignment, $role);
            $this->postJson('/api/mobile/v1/students', [])->assertForbidden();
            $this->getJson('/api/mobile/v1/students/'.$student->id)->assertForbidden();
            $this->patchJson('/api/mobile/v1/students/'.$student->id, [])->assertForbidden();
            $this->deleteJson('/api/mobile/v1/students/'.$student->id)->assertForbidden();
        }
    }

    public function test_scope_assignment_and_validation_prevent_mass_assignment(): void
    {
        $school = $this->school('Own');
        $this->login($school, scope: true);
        $this->postJson('/api/mobile/v1/students', [])->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name', 'school_year']);
        $valid = ['first_name' => 'First', 'last_name' => 'Last', 'school_year' => '2026-2027'];
        $this->postJson('/api/mobile/v1/students', $valid + ['school_id' => 999, 'iin' => '1234567890123', 'meal_benefit_type' => 'unknown'])
            ->assertUnprocessable()->assertJsonValidationErrors(['school_id', 'iin', 'meal_benefit_type']);
        $this->postJson('/api/mobile/v1/students', array_replace($valid, ['school_year' => '2026-2028', 'classroom_id' => 999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['school_year', 'classroom_id']);
        $id = $this->postJson('/api/mobile/v1/students', $valid + ['status' => 'graduated', 'classroom_history' => [999]])
            ->assertCreated()->json('student.id');
        $this->assertDatabaseHas('students', ['id' => $id, 'school_id' => $school->id, 'status' => 'active']);
        $this->patchJson('/api/mobile/v1/students/'.$id, ['school_id' => 999])->assertUnprocessable();
        $this->patchJson('/api/mobile/v1/students/'.$id, ['first_name' => null])->assertUnprocessable();
    }

    public function test_web_form_fields_and_iin_derivation_and_benefit_history(): void
    {
        $school = $this->school('Own');
        $this->login($school);
        $body = [
            'first_name' => 'Dana', 'last_name' => 'Student', 'iin' => '160315512345', 'school_year' => '2026-2027',
            'meal_benefit_type' => 'susn', 'phone' => '+7 701 123 45 67',
            'address' => 'Test address', 'language' => 'kk', 'shift' => 2,
        ];
        $response = $this->postJson('/api/mobile/v1/students', $body)->assertCreated()
            ->assertJsonPath('student.birth_date', '2016-03-15')->assertJsonPath('student.gender', 'male')
            ->assertJsonPath('student.phone', '+77011234567')->assertJsonPath('student.meal_benefit_type', 'susn');
        $id = $response->json('student.id');
        $this->getJson('/api/mobile/v1/students/'.$id)->assertOk()->assertJsonPath('student.iin', $body['iin'])
            ->assertJsonPath('student.address', 'Test address');
        $this->postJson('/api/mobile/v1/students', $body)->assertUnprocessable()->assertJsonValidationErrors(['iin']);
        $this->patchJson('/api/mobile/v1/students/'.$id, [
            'iin' => $body['iin'], 'birth_date' => '2016-04-01', 'gender' => 'female', 'meal_benefit_type' => 'paid',
        ])->assertOk()->assertJsonPath('student.birth_date', '2016-04-01')->assertJsonPath('student.gender', 'female')
            ->assertJsonPath('student.meal_benefit_type', 'paid');
        $this->patchJson('/api/mobile/v1/students/'.$id, ['meal_benefit_type' => 'paid'])->assertOk();
        $this->assertDatabaseCount('meal_benefits', 2);
        $this->patchJson('/api/mobile/v1/students/'.$id, ['meal_benefit_type' => null, 'phone' => null])->assertOk()
            ->assertJsonPath('student.meal_benefit_type', 'paid')->assertJsonPath('student.phone', null);
        $this->getJson('/api/mobile/v1/students')->assertOk()->assertJsonMissingPath('students.0.iin')
            ->assertJsonMissingPath('students.0.meal_benefit_type');
    }

    public function test_teacher_can_upload_replace_and_delete_photo(): void
    {
        Storage::fake('public');
        $school = $this->school('Own');
        $this->login($school);
        $student = Student::query()->create(['school_id' => $school->id]);
        $url = '/api/mobile/v1/students/'.$student->id.'/photo';
        $this->post($url, ['photo_file' => UploadedFile::fake()->image('first.jpg')->size(2048)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('student.photo', true);
        $firstPath = $student->fresh()->photo;
        Storage::disk('public')->assertExists($firstPath);
        $student->refresh()->forceFill(['photo_synced_at' => now()])->save();
        $this->post($url, ['photo_file' => UploadedFile::fake()->image('second.png')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('student.photo', true);
        $student->refresh();
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($student->photo);
        $this->assertNull($student->photo_synced_at);
        $lastPath = $student->photo;
        $this->getJson('/api/mobile/v1/students')->assertOk()->assertJsonPath('students.0.photo_url', $student->photo_url);
        $this->deleteJson($url)->assertOk()->assertJsonPath('student.photo_url', null)->assertJsonPath('student.photo', false);
        Storage::disk('public')->assertMissing($lastPath);
        $this->assertNull($student->fresh()->photo);
        $this->deleteJson($url)->assertOk();
    }

    public function test_photo_validation_and_school_authorization(): void
    {
        Storage::fake('public');
        $school = $this->school('Own');
        $student = Student::query()->create(['school_id' => $school->id]);
        $url = '/api/mobile/v1/students/'.$student->id.'/photo';
        $this->postJson($url)->assertUnauthorized();
        $this->deleteJson($url)->assertUnauthorized();
        $this->login($school);
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors(['photo_file']);
        foreach ([UploadedFile::fake()->create('text.txt', 1, 'text/plain'), UploadedFile::fake()->image('large.jpg')->size(2049)] as $file) {
            $this->post($url, ['photo_file' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors(['photo_file']);
        }
        foreach ([$this->school('Other')->id, null] as $schoolId) {
            $foreign = Student::query()->create(['school_id' => $schoolId]);
            $foreignUrl = '/api/mobile/v1/students/'.$foreign->id.'/photo';
            $this->postJson($foreignUrl)->assertNotFound();
            $this->deleteJson($foreignUrl)->assertNotFound();
        }
    }

    public function test_photo_requires_teacher_and_school(): void
    {
        $school = $this->school('Own');
        $student = Student::query()->create(['school_id' => $school->id]);
        $url = '/api/mobile/v1/students/'.$student->id.'/photo';
        $this->login(null);
        $this->postJson($url)->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        $this->login($school, 'director');
        $this->postJson($url)->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
    }

    public function test_archive_preserves_orders_and_photo_and_can_be_restored(): void
    {
        Storage::fake('public');
        $school = $this->school('Own');
        $other = $this->school('Other');
        $this->login($school);
        Storage::disk('public')->put('user/photos/archive.jpg', 'photo');
        $student = Student::query()->create([
            'school_id' => $school->id, 'first_name' => 'Archive', 'last_name' => 'Student',
            'photo' => 'user/photos/archive.jpg', 'status' => 'graduated',
        ]);
        $student->mealBenefits()->create(['type' => 'susn']);
        $order = $student->orders()->create(['order_date' => '2026-10-20', 'status' => 'completed']);
        $url = '/api/mobile/v1/students/'.$student->id;
        $this->deleteJson($url)->assertNoContent();
        $this->deleteJson($url)->assertNoContent();
        $this->assertSoftDeleted($student);
        Storage::disk('public')->assertExists('user/photos/archive.jpg');
        $this->getJson('/api/mobile/v1/students')->assertOk()->assertJsonCount(0, 'students');
        $this->getJson('/api/mobile/v1/students?archived=1')->assertOk()->assertJsonPath('students.0.id', $student->id);
        $this->getJson($url)->assertOk()->assertJsonPath('student.id', $student->id);
        $this->patchJson($url, ['first_name' => 'Changed'])->assertNotFound();
        $this->postJson($url.'/photo')->assertNotFound();
        $this->getJson($url.'/orders')->assertOk()->assertJsonPath('orders.0.id', $order->id);
        $this->getJson('/api/mobile/v1/orders')->assertOk()->assertJsonPath('orders.0.id', $order->id);
        $this->assertFalse($student->fresh()->canCreateOrder());
        $this->assertFalse(app(\App\Services\Orders\OrderEligibilityService::class)
            ->evaluate($student->fresh(), '2026-2027', '2026-10-20')['eligible']);
        $this->postJson($url.'/restore')->assertOk()->assertJsonPath('student.archived_at', null);
        $this->assertSame('graduated', $student->fresh()->status);
        $this->getJson('/api/mobile/v1/students')->assertOk()->assertJsonCount(1, 'students');
        $foreign = Student::query()->create(['school_id' => $other->id]);
        $foreign->delete();
        $this->postJson('/api/mobile/v1/students/'.$foreign->id.'/restore')->assertNotFound();
        $this->assertSoftDeleted($foreign);
    }

    public function test_form_references_use_school_years_and_shared_sorted_classrooms(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22'));
        $school = $this->school('Own');
        $other = $this->school('Other');
        Student::query()->create(['school_id' => $school->id, 'school_year' => '2025-2026']);
        Student::query()->create(['school_id' => $other->id, 'school_year' => '2010-2011']);
        AcademicClass::query()->create(['grade' => 0, 'letter' => 'A']);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $this->login($school, scope: true);
        $this->getJson('/api/mobile/v1/classrooms')->assertOk()->assertJsonCount(1, 'classrooms')
            ->assertJsonPath('classrooms.0.id', $class->id);
        $this->getJson('/api/mobile/v1/students/options')->assertOk()
            ->assertJsonPath('default_school_year', '2026-2027')
            ->assertJsonPath('school_years', ['2026-2027', '2025-2026'])
            ->assertJsonPath('languages.0.value', 'ru')->assertJsonPath('shifts.1.value', 2)
            ->assertJsonPath('meal_benefit_types.0.value', 'susn')->assertJsonPath('photo_max_kb', 2048);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-08-31'));
        $this->getJson('/api/mobile/v1/students/options')->assertOk()->assertJsonPath('default_school_year', '2025-2026');
    }

    public function test_reference_and_restore_routes_require_teacher_assignment(): void
    {
        $school = $this->school('Own');
        $student = Student::query()->create(['school_id' => $school->id]);
        $student->delete();
        foreach (['/api/mobile/v1/classrooms', '/api/mobile/v1/students/options'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->postJson('/api/mobile/v1/students/'.$student->id.'/restore')->assertUnauthorized();
        foreach ([[null, 'teacher'], [$school, 'director']] as [$assignment, $role]) {
            $this->login($assignment, $role);
            $this->getJson('/api/mobile/v1/classrooms')->assertForbidden();
            $this->getJson('/api/mobile/v1/students/options')->assertForbidden();
            $this->postJson('/api/mobile/v1/students/'.$student->id.'/restore')->assertForbidden();
        }
    }

    public function test_guests_cannot_manage_students(): void
    {
        $this->postJson('/api/mobile/v1/students', [])->assertUnauthorized();
        $this->getJson('/api/mobile/v1/students/1')->assertUnauthorized();
        $this->patchJson('/api/mobile/v1/students/1', [])->assertUnauthorized();
        $this->deleteJson('/api/mobile/v1/students/1')->assertUnauthorized();
    }
}

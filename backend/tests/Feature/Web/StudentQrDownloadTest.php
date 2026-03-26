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

class StudentQrDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_qr_download_returns_svg_with_name_and_class(): void
    {
        [$school] = $this->makeSchools();
        $classroom = AcademicClass::query()->create([
            'grade' => 5,
            'letter' => 'А',
        ]);
        $user = User::factory()->create(['school_id' => $school->id]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'middle_name' => 'Иванович',
            'iin' => '123456789012',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->get(route('students.qr', ['student' => $student, 'download' => 1]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Content-Disposition');
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_class_qr_download_returns_zip_archive(): void
    {
        [$school] = $this->makeSchools();
        $classroom = AcademicClass::query()->create([
            'grade' => 6,
            'letter' => 'Б',
        ]);
        $user = User::factory()->create(['school_id' => $school->id]);

        Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'first_name' => 'Петр',
            'last_name' => 'Петров',
            'iin' => '123456789013',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->get(route('classes.qr.download', $classroom));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }


    public function test_super_admin_can_download_student_qr_without_school_binding(): void
    {
        [$school] = $this->makeSchools();
        $classroom = AcademicClass::query()->create([
            'grade' => 7,
            'letter' => 'A',
        ]);
        $role = Role::query()->create([
            'code' => 'super_admin',
            'name' => 'Super Admin',
            'description' => 'Super Admin',
            'is_system' => true,
        ]);
        $user = User::factory()->create(['school_id' => null]);
        $user->roles()->attach($role->id);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'first_name' => 'Admin',
            'last_name' => 'Student',
            'iin' => '123456789014',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->get(route('students.qr', ['student' => $student, 'download' => 1]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition');
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }
    private function makeSchools(): array
    {
        $region = Region::query()->create([
            'name' => 'Region',
            'name_ru' => 'Region',
            'name_kk' => 'Region',
            'code' => 'reg-1',
        ]);

        $district = District::query()->create([
            'region_id' => $region->id,
            'name' => 'District',
            'name_ru' => 'District',
            'name_kk' => 'District',
            'code' => 'dist-1',
        ]);

        $schoolA = School::query()->create([
            'district_id' => $district->id,
            'name' => 'School A',
            'name_ru' => 'School A',
            'name_kk' => 'School A',
            'code' => 'school-a',
            'bin' => '111111111111',
            'address' => 'Address A',
            'kitchen_access_token' => 'kitchen-token-a',
            'is_active' => true,
        ]);

        $schoolB = School::query()->create([
            'district_id' => $district->id,
            'name' => 'School B',
            'name_ru' => 'School B',
            'name_kk' => 'School B',
            'code' => 'school-b',
            'bin' => '222222222222',
            'address' => 'Address B',
            'kitchen_access_token' => 'kitchen-token-b',
            'is_active' => true,
        ]);

        return [$schoolA, $schoolB];
    }
}
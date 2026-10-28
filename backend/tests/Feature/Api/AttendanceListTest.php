<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use App\Models\VerifyEvent;
use App\Modules\Access\Models\Role;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_roles_see_only_their_school_and_latest_student_state(): void
    {
        $region = \App\Modules\Organizations\Models\Region::query()->create(['name_ru' => 'Region', 'code' => 'region']);
        $district = \App\Modules\Organizations\Models\District::query()->create(['region_id' => $region->id, 'name_ru' => 'District', 'code' => 'district']);
        $school = School::query()->create(['district_id' => $district->id, 'name_ru' => 'School', 'code' => 'one', 'bin' => '111']);
        $otherSchool = School::query()->create(['district_id' => $district->id, 'name_ru' => 'Other', 'code' => 'two', 'bin' => '222']);
        $student = Student::query()->create(['school_id' => $school->id, 'first_name' => 'Alice']);
        $other = Student::query()->create(['school_id' => $otherSchool->id, 'first_name' => 'Hidden']);
        foreach ([[$student, '111', 'exit', '12:00:00'], [$student, '111', 'entry', '08:00:00'], [$other, '222', null, '13:00:00'], [$student, '222', 'entry', '14:00:00']] as [$person, $bin, $direction, $time]) {
            VerifyEvent::query()->create(['unique_qr' => (string) $person->id, 'bin' => $bin, 'direction' => $direction, 'create_time' => '2026-09-17 '.$time]);
        }
        foreach (['2026-09-16 07:00:00', '2026-09-17 13:00:00', '2026-09-17 14:00:00'] as $time) {
            VerifyEvent::query()->create(['unique_qr' => (string) $other->id, 'bin' => '222', 'create_time' => $time]);
        }
        foreach (['teacher', 'director'] as $code) {
            $user = User::factory()->create(['school_id' => $school->id]);
            $role = Role::query()->create(['code' => $code, 'name' => $code]);
            $user->roles()->attach($role);
            $this->actingAs($user, 'api')->getJson('/api/mobile/v1/attendance?date=2026-09-17')
                ->assertOk()->assertJsonPath('stats.total', 1)->assertJsonPath('stats.outside', 1)
                ->assertJsonPath('stats.inside', 0)->assertJsonPath('meta.total', 1)
                ->assertJsonPath('events.0.student.full_name', 'Alice')
                ->assertJsonPath('latest.direction', 'exit')->assertJsonMissing(['full_name' => 'Hidden']);
            $this->getJson('/api/mobile/v1/attendance?date=2026-09-17&direction=entry&search=Alice&per_page=1')
                ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.per_page', 1);
            $this->getJson('/api/mobile/v1/attendance?date=2026-09-18')->assertOk()
                ->assertJsonPath('meta.total', 0)->assertJsonPath('latest', null)->assertJsonPath('stats.absent', 1);
            $this->getJson('/api/mobile/v1/attendance?date=bad&direction=bad&page=0&per_page=101')
                ->assertUnprocessable()->assertJsonValidationErrors(['date', 'direction', 'page', 'per_page']);
        }
    }

    public function test_authentication_is_required(): void
    {
        $this->getJson('/api/mobile/v1/attendance')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/mobile/v1/attendance')->assertForbidden();
    }
}

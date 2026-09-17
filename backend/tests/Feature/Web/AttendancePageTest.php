<?php

namespace Tests\Feature\Web;

use App\Models\Student;
use App\Models\User;
use App\Models\VerifyEvent;
use App\Modules\Access\Models\Role;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePageTest extends TestCase
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
            $this->actingAs($user)->get('/attendance?date=2026-09-17')->assertOk()
                ->assertSee('Alice')->assertDontSee('Hidden')
                ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1 && $stats['outside'] === 1 && $stats['inside'] === 0)
                ->assertViewHas('events', fn ($events) => $events->total() === 1 && $events->first()->create_time->format('H:i:s') === '08:00:00');
            $this->get('/attendance?date=2026-09-17&direction=entry&search=Alice')->assertOk()
                ->assertViewHas('events', fn ($events) => $events->total() === 1);
            $this->get('/attendance?date=2026-09-18')->assertOk()
                ->assertViewHas('events', fn ($events) => $events->total() === 0);
        }
        foreach (['super_admin', 'support_admin'] as $code) {
            $admin = User::factory()->create(['school_id' => null]);
            $role = Role::query()->create(['code' => $code, 'name' => $code]);
            $admin->roles()->attach($role);
            $this->actingAs($admin)->get('/attendance?date=2026-09-17')->assertOk()
                ->assertSee('href="'.route('attendance.index').'"', false)
                ->assertSee('Alice')->assertSee('Hidden')
                ->assertViewHas('events', fn ($events) => $events->total() === 2)
                ->assertViewHas('stats', fn ($stats) => $stats['total'] === 2 && $stats['inside'] === 1 && $stats['outside'] === 1);
        }
    }

    public function test_other_roles_cannot_open_attendance(): void
    {
        $this->get('/attendance')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/attendance')->assertForbidden();
    }
}

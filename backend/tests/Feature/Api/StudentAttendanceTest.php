<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use App\Models\VerifyEvent;
use App\Modules\Access\Models\Role;
use App\Modules\Identity\Application\Actions\IssueApiTokenAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'teacher', bool $scope = false): array
    {
        $region = DB::table('regions')->insertGetId(['name' => 'Region', 'code' => 'r']);
        $district = DB::table('districts')->insertGetId(['name' => 'District', 'code' => 'd', 'region_id' => $region]);
        $school = DB::table('schools')->insertGetId(['name' => 'School', 'code' => 's', 'district_id' => $district, 'bin' => '111']);
        $other = DB::table('schools')->insertGetId(['name' => 'Other', 'code' => 'o', 'district_id' => $district, 'bin' => '222']);
        $user = User::factory()->create(['school_id' => $scope ? null : $school]);
        $user->roles()->attach(Role::query()->create(['code' => $role, 'name' => $role]));
        if ($scope) {
            $user->scopes()->create(['scope_type' => 'school', 'scope_id' => $school]);
        }
        $student = Student::create(['school_id' => $school, 'first_name' => 'Alice']);
        $peer = Student::create(['school_id' => $school, 'first_name' => 'Peer']);
        $foreign = Student::create(['school_id' => $other, 'first_name' => 'Hidden']);
        $events = [];
        foreach ([['2026-10-20 08:00:00', 'entry'], ['2026-10-20 12:00:00', 'exit'], ['2026-10-21 09:00:00', null], ['2026-10-21 09:00:00', 'entry']] as [$time, $direction]) {
            $events[] = VerifyEvent::create(['unique_qr' => (string) $student->id, 'bin' => '111', 'create_time' => $time, 'direction' => $direction]);
        }
        foreach ([[$student, '222'], [$peer, '111'], [$foreign, '222']] as [$person, $bin]) {
            VerifyEvent::create(['unique_qr' => (string) $person->id, 'bin' => $bin, 'create_time' => '2026-10-22 08:00:00']);
        }
        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);

        return compact('user', 'school', 'other', 'student', 'peer', 'foreign', 'events');
    }

    public function test_history_returns_every_scan_in_stable_order_and_ignores_scope_overrides(): void
    {
        $f = $this->fixture(scope: true);
        $url = '/api/mobile/v1/students/'.$f['student']->id.'/attendance';
        $this->getJson($url.'?school_id='.$f['other'].'&student_id='.$f['peer']->id.'&per_page=2')
            ->assertOk()->assertJsonPath('student.full_name', 'Alice')->assertJsonPath('meta.total', 4)
            ->assertJsonPath('events.0.id', $f['events'][3]->id)->assertJsonPath('events.1.id', $f['events'][2]->id)
            ->assertJsonPath('events.1.direction', null)->assertJsonMissingPath('student.iin');
        $this->getJson($url.'?per_page=2&page=2')->assertOk()
            ->assertJsonPath('events.0.id', $f['events'][1]->id)->assertJsonPath('events.1.id', $f['events'][0]->id);
        foreach ([$f['foreign']->id, 999999] as $id) {
            $this->getJson('/api/mobile/v1/students/'.$id.'/attendance')->assertNotFound();
        }
    }

    public function test_dates_directions_empty_history_and_archived_students(): void
    {
        $f = $this->fixture('director');
        $url = '/api/mobile/v1/students/'.$f['student']->id.'/attendance';
        $this->getJson($url.'?date=2026-10-20')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson($url.'?date_from=2026-10-21&date_to=2026-10-21')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson($url.'?date_to=2026-10-20&direction=exit')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('events.0.direction', 'exit');
        $this->getJson($url.'?date_from=2026-10-21&direction=entry')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($url.'?date=2026-10-22')->assertOk()->assertJsonCount(0, 'events');
        $f['student']->delete();
        $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson($url.'?date=bad&date_from=2026-10-22&date_to=2026-10-20&direction=bad&page=0&per_page=101')
            ->assertUnprocessable()->assertJsonValidationErrors(['date', 'date_to', 'direction', 'page', 'per_page']);
    }

    public function test_authentication_roles_and_school_assignment(): void
    {
        $this->getJson('/api/mobile/v1/students/1/attendance')->assertUnauthorized();
        $f = $this->fixture('library');
        $url = '/api/mobile/v1/students/'.$f['student']->id.'/attendance';
        $this->getJson($url)->assertForbidden();
        $f['user']->roles()->detach();
        $f['user']->roles()->attach(Role::query()->create(['code' => 'teacher', 'name' => 'Teacher']));
        $f['user']->update(['school_id' => null]);
        app('auth')->forgetGuards();
        $this->getJson($url)->assertForbidden();
    }
}

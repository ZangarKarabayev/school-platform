<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Identity\Application\Actions\IssueApiTokenAction;
use App\Services\Dashboard\DashboardDataService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_roleless_user_cannot_access_dashboard_with_forged_scope(): void
    {
        $user = User::factory()->create(['school_id' => null]);
        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);
        $this->getJson('/api/mobile/v1/dashboard?school_id=1&scope_kind=region&region_id=1')
            ->assertForbidden()->assertJsonMissingPath('ordersTable');
    }

    public function test_report_permission_without_scope_is_denied(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['school_id' => null]);
        $user->roles()->attach(Role::query()->where('code', 'director')->firstOrFail());
        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);
        $this->getJson('/api/mobile/v1/dashboard')->assertForbidden();
    }

    public function test_service_rejects_unassigned_user_even_without_controller(): void
    {
        $this->expectException(HttpException::class);
        app(DashboardDataService::class)->build(User::factory()->create(['school_id' => null]));
    }

    public function test_teacher_dashboard_cannot_escape_school_even_with_broader_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $region = \App\Modules\Organizations\Models\Region::query()->create(['name' => 'Region', 'code' => 'r']);
        $district = \App\Modules\Organizations\Models\District::query()->create(['name' => 'District', 'code' => 'd', 'region_id' => $region->id]);
        $school = \App\Modules\Organizations\Models\School::query()->create(['name' => 'School', 'code' => 's', 'district_id' => $district->id]);
        Student::query()->create(['school_id' => $school->id, 'status' => 'active']);
        Student::query()->create(['school_id' => null, 'status' => 'active']);
        $other = \App\Modules\Organizations\Models\School::query()->create(['name' => 'Other', 'code' => 'o', 'district_id' => $district->id]);
        Student::query()->create(['school_id' => $other->id, 'status' => 'active']);
        $user = User::factory()->create(['school_id' => $school->id, 'region_id' => $region->id]);
        $user->roles()->attach(Role::query()->whereIn('code', ['teacher', 'region_operator', 'super_admin'])->pluck('id'));
        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);
        $this->getJson('/api/mobile/v1/dashboard?school_id=99999&scope_kind=region')
            ->assertOk()->assertJsonPath('charts.coverage.0.value', 1)->assertJsonPath('filters.scope_kind', 'school')
            ->assertJsonPath('filters.school_id', $school->id);
    }

    public function test_teacher_needs_school_and_director_is_denied(): void
    {
        $this->seed(RolePermissionSeeder::class);
        foreach (['teacher', 'director'] as $role) {
            $user = User::factory()->create(['school_id' => null]);
            $user->roles()->attach(Role::query()->where('code', $role)->firstOrFail());
            app('auth')->forgetGuards();
            $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);
            $this->getJson('/api/mobile/v1/dashboard?school_id=1')->assertForbidden();
        }
    }
}

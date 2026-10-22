<?php

namespace Tests\Feature\Services;

use App\Models\AcademicClass;
use App\Models\Order;
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

class OrderCreationWorkflowTest extends TestCase
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

    private function order(Student $student, string $date = '2026-10-20', array $data = []): Order
    {
        return Order::query()->create($data + ['student_id' => $student->id, 'order_date' => $date, 'status' => 'created']);
    }

    public function test_web_and_mobile_share_creation_and_do_not_redispatch_existing_orders(): void
    {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\CreateOrdersJob::class, \App\Jobs\SendSocialWalletTransactionJob::class]);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00'));
        $school = $this->school('Own');
        $teacher = $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $body = ['target_type' => 'classes', 'classroom_ids' => [$class->id], 'school_year' => '2026-2027', 'order_date' => '2026-10-20'];
        $this->postJson('/api/mobile/v1/orders', ['student_ids' => [$student->id], 'order_date' => $body['order_date'], 'school_year' => $body['school_year']])->assertOk()->assertJsonPath('created_count', 1);
        $this->actingAs($teacher)->post(route('orders.store'), $body)->assertRedirect(route('orders.index'))->assertSessionHasNoErrors();
        $job = \Illuminate\Support\Facades\Bus::dispatched(\App\Jobs\CreateOrdersJob::class)->first();
        $this->assertNotNull($job);
        $this->assertSame($school->id, $job->schoolId);
        $this->assertSame([$student->id], $job->studentIds);
        $job->handle();
        $this->assertDatabaseCount('orders', 1);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\SendSocialWalletTransactionJob::class, 1);
    }

    public function test_worker_rechecks_school_after_student_or_teacher_transfer(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $school = $this->school('Own');
        $other = $this->school('Other');
        $teacher = $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $job = new \App\Jobs\CreateOrdersJob([$student->id], '2026-10-20', null, $teacher->id, '2026-2027', $school->id);
        $student->update(['school_id' => $other->id]);
        $job->handle();
        $this->assertDatabaseCount('orders', 0);
        $student->update(['school_id' => $school->id]);
        $teacher->update(['school_id' => $other->id]);
        $job->handle();
        $this->assertDatabaseCount('orders', 0);
        \Illuminate\Support\Facades\Bus::assertNothingDispatched();
    }

    public function test_worker_uses_same_benefit_rules_for_older_students(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $school = $this->school('Own');
        $teacher = $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 7, 'letter' => 'A']);
        $eligible = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $eligible->mealBenefits()->create(['type' => 'susn']);
        $paid = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $paid->mealBenefits()->create(['type' => 'paid']);
        $job = new \App\Jobs\CreateOrdersJob([$eligible->id, $paid->id], '2026-10-20', null, $teacher->id, '2026-2027', $school->id);
        $job->handle();
        $job->handle();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['student_id' => $eligible->id]);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\SendSocialWalletTransactionJob::class, 1);
    }

    public function test_web_uses_same_date_validation_and_school_scope(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00'));
        $school = $this->school('Own');
        $other = $this->school('Other');
        $teacher = $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        Student::query()->create(['school_id' => $other->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $body = ['target_type' => 'classes', 'classroom_ids' => [$class->id], 'school_year' => '2026-2027', 'order_date' => '2026-10-23'];
        $this->actingAs($teacher)->post(route('orders.store'), $body)->assertRedirect(route('orders.index'))->assertSessionHasErrors('order_date');
        $body['order_date'] = '2026-10-20';
        $this->post(route('orders.store'), $body)->assertRedirect(route('orders.index'))->assertSessionHasErrors('target_type');
        \Illuminate\Support\Facades\Bus::assertNothingDispatched();
    }
}

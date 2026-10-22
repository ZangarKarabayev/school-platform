<?php

namespace Tests\Feature\Api;

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

class OrderCreateTest extends TestCase
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

    public function test_batch_creation_marks_students_and_retries_do_not_duplicate_or_redispatch(): void
    {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\SendSocialWalletTransactionJob::class]);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00', config('app.timezone')));
        $school = $this->school('Own');
        $user = $this->login($school, scope: true);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $olderClass = AcademicClass::query()->create(['grade' => 7, 'letter' => 'A']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $existing = Student::query()->create(['school_id' => $school->id]);
        $skipped = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $olderClass->id, 'school_year' => '2026-2027']);
        $oldOrder = $this->order($existing, '2026-10-20');
        $body = ['student_ids' => [$student->id, $existing->id, $skipped->id], 'order_date' => '2026-10-20', 'order_time' => '12:00', 'school_year' => '2026-2027'];
        $this->postJson('/api/mobile/v1/orders', $body)->assertOk()->assertJsonPath('created_count', 1)
            ->assertJsonPath('already_exists_count', 1)->assertJsonPath('skipped_count', 1);
        $order = Order::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame($user->id, $order->created_by_user_id);
        $this->assertSame($class->id, $order->classroom_id);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\SendSocialWalletTransactionJob::class, 1);
        $this->postJson('/api/mobile/v1/orders', $body)->assertOk()->assertJsonPath('created_count', 0)->assertJsonPath('already_exists_count', 2);
        $this->assertDatabaseCount('orders', 2);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\SendSocialWalletTransactionJob::class, 1);
        $students = $this->getJson('/api/mobile/v1/students?order_date=2026-10-20')->assertOk()->json('students');
        $indexed = collect($students)->keyBy('id');
        $this->assertTrue($indexed[$student->id]['has_order']);
        $this->assertSame($order->id, $indexed[$student->id]['order_id']);
        $this->assertFalse($indexed[$skipped->id]['has_order']);
        $this->assertNull($indexed[$skipped->id]['order_id']);
        $this->getJson('/api/mobile/v1/students?order_date=2026-10-21')->assertOk()->assertJsonPath('students.0.has_order', false);
    }

    public function test_foreign_archived_missing_students_reject_entire_batch(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00'));
        $school = $this->school('Own');
        $other = $this->school('Other');
        $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $own = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $foreign = Student::query()->create(['school_id' => $other->id]);
        $archived = Student::query()->create(['school_id' => $school->id]);
        $archived->delete();
        foreach ([$foreign->id, $archived->id, 99999] as $id) {
            $this->postJson('/api/mobile/v1/orders', ['student_ids' => [$own->id, $id], 'order_date' => '2026-10-20', 'school_year' => '2026-2027'])
                ->assertForbidden();
        }
        $this->assertDatabaseCount('orders', 0);
        \Illuminate\Support\Facades\Bus::assertNothingDispatched();
    }

    public function test_invalid_dates_ids_and_future_time_are_rejected(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00', config('app.timezone')));
        $school = $this->school('Own');
        $this->login($school);
        $student = Student::query()->create(['school_id' => $school->id]);
        $body = ['student_ids' => [$student->id], 'order_date' => '2026-10-20', 'school_year' => '2026-2027'];
        foreach ([['order_date' => '2026-10-23'], ['order_date' => '2026-10-22', 'order_time' => '15:00'],
            ['order_date' => '2026-10-17'], ['order_date' => '2026-10-26'], ['school_year' => '2025-2026'],
            ['student_ids' => []], ['student_ids' => [$student->id, $student->id]], ['order_time' => 'wrong']] as $changes) {
            $this->postJson('/api/mobile/v1/orders', array_replace($body, $changes))->assertUnprocessable();
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_creation_requires_teacher_permission_and_assignment(): void
    {
        $this->postJson('/api/mobile/v1/orders', [])->assertUnauthorized();
        $school = $this->school('Own');
        foreach ([[null, 'teacher'], [$school, 'director'], [$school, 'super_admin']] as [$assignment, $role]) {
            $this->login($assignment, $role);
            $this->postJson('/api/mobile/v1/orders', [])->assertForbidden();
        }
        $user = $this->login($school);
        $user->roles()->first()->permissions()->detach();
        app('auth')->forgetGuards();
        $this->postJson('/api/mobile/v1/orders', [])->assertForbidden();
    }

    public function test_selected_students_use_enrollment_year_and_skip_existing_orders(): void
    {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\SendSocialWalletTransactionJob::class]);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00'));
        $school = $this->school('Own');
        $other = $this->school('Other');
        $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $next = AcademicClass::query()->create(['grade' => 4, 'letter' => 'A']);
        $new = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $existing = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $this->order($existing);
        // Current class differs, but membership for the requested year is authoritative.
        $new->update(['classroom_id' => $next->id, 'school_year' => '2027-2028']);
        Student::query()->create(['school_id' => $other->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2025-2026']);
        Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027', 'status' => 'graduated']);
        $archived = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $archived->delete();
        $body = ['student_ids' => [$new->id, $existing->id], 'order_date' => '2026-10-20', 'school_year' => '2026-2027'];
        $this->postJson('/api/mobile/v1/orders', $body)->assertOk()->assertJsonPath('created_count', 1)
            ->assertJsonPath('already_exists_count', 1)->assertJsonCount(2, 'results');
        $this->assertDatabaseHas('orders', ['student_id' => $new->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $this->postJson('/api/mobile/v1/orders', $body)->assertOk()->assertJsonPath('created_count', 0)->assertJsonPath('already_exists_count', 2);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\SendSocialWalletTransactionJob::class, 1);
    }

    public function test_selection_is_not_truncated_to_one_hundred_students(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00'));
        $school = $this->school('Own');
        $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        foreach (range(1, 101) as $i) {
            Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        }
        $this->postJson('/api/mobile/v1/orders', ['student_ids' => Student::query()->where('school_id', $school->id)->pluck('id')->all(), 'order_date' => '2026-10-20', 'school_year' => '2026-2027'])
            ->assertOk()->assertJsonPath('created_count', 101)->assertJsonCount(101, 'results');
        $this->assertDatabaseCount('orders', 101);
    }

    public function test_only_submitted_students_are_selected_and_class_mode_is_rejected(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00'));
        $school = $this->school('Own');
        $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $selected = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $unselected = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $body = ['student_ids' => [$selected->id], 'order_date' => '2026-10-20', 'school_year' => '2026-2027'];
        $this->postJson('/api/mobile/v1/orders', $body)->assertOk()->assertJsonPath('created_count', 1);
        $this->assertDatabaseMissing('orders', ['student_id' => $unselected->id]);
        $this->postJson('/api/mobile/v1/orders', $body + ['target_type' => 'classes', 'classroom_ids' => [$class->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['target_type', 'classroom_ids']);
    }

    public function test_school_year_is_derived_from_order_date_not_student_current_year(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00'));
        $school = $this->school('Own');
        $this->login($school);
        $class = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2025-2026']);
        $student->update(['school_year' => '2026-2027']);
        foreach (['2026-05-25' => '2025-2026', '2026-09-01' => '2026-2027'] as $date => $year) {
            $this->postJson('/api/mobile/v1/orders', ['student_ids' => [$student->id], 'order_date' => $date])
                ->assertOk()->assertJsonPath('created_count', 1);
            $order = Order::query()->whereDate('order_date', $date)->firstOrFail();
            $this->assertSame($year, $order->school_year);
        }
        $this->postJson('/api/mobile/v1/orders', ['student_ids' => [$student->id], 'order_date' => '2026-10-20', 'school_year' => '2025-2026'])
            ->assertUnprocessable()->assertJsonValidationErrors(['order_date']);
    }
}

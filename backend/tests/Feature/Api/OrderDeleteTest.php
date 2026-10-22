<?php

namespace Tests\Feature\Api;

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

class OrderDeleteTest extends TestCase
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

    public function test_delete_keeps_history_and_allows_new_active_order_on_the_same_date(): void
    {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\SendSocialWalletTransactionJob::class]);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 14:00:00'));
        $school = $this->school('Own');
        $this->login($school, scope: true);
        $class = \App\Models\AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $class->id, 'school_year' => '2026-2027']);
        $order = $this->order($student, data: ['status' => 'completed', 'transaction_status' => true]);
        $url = '/api/mobile/v1/orders/'.$order->id;
        $this->deleteJson($url)->assertNoContent();
        $this->assertSoftDeleted($order);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed', 'transaction_status' => true]);
        $this->deleteJson($url)->assertNoContent();
        $this->getJson('/api/mobile/v1/orders')->assertOk()->assertJsonCount(0, 'orders');
        $this->getJson('/api/mobile/v1/students/'.$student->id.'/orders')->assertOk()->assertJsonCount(0, 'orders');
        $this->getJson('/api/mobile/v1/students?order_date=2026-10-20')->assertOk()->assertJsonPath('students.0.has_order', false);
        $this->postJson('/api/mobile/v1/orders', ['student_ids' => [$student->id], 'order_date' => '2026-10-20', 'school_year' => '2026-2027'])
            ->assertOk()->assertJsonPath('created_count', 1);
        $this->assertSame(2, Order::withTrashed()->count());
        $this->assertSame(1, Order::query()->count());
        $newOrder = Order::query()->firstOrFail();
        $this->assertNotSame($order->id, $newOrder->id);
        $this->getJson('/api/mobile/v1/students?order_date=2026-10-20')->assertOk()
            ->assertJsonPath('students.0.has_order', true)->assertJsonPath('students.0.order_id', $newOrder->id);
    }

    public function test_queued_transaction_is_not_sent_for_deleted_order(): void
    {
        $school = $this->school('Own');
        $this->login($school);
        $student = Student::query()->create(['school_id' => $school->id]);
        $order = $this->order($student);
        $this->deleteJson('/api/mobile/v1/orders/'.$order->id)->assertNoContent();
        $service = \Mockery::mock(\App\Services\SocialWallet\SocialWalletService::class);
        $service->shouldNotReceive('sendMealTransaction');
        (new \App\Jobs\SendSocialWalletTransactionJob($order->id))->handle($service);
    }

    public function test_cannot_delete_other_school_orders_or_without_permissions(): void
    {
        $school = $this->school('Own');
        $other = $this->school('Other');
        $own = Student::query()->create(['school_id' => $school->id]);
        $order = $this->order($own);
        $url = '/api/mobile/v1/orders/'.$order->id;
        $this->deleteJson($url)->assertUnauthorized();
        foreach ([[null, 'teacher'], [$school, 'director'], [$school, 'super_admin']] as [$assignment, $role]) {
            $this->login($assignment, $role);
            $this->deleteJson($url)->assertForbidden();
        }
    }

    public function test_foreign_missing_and_archived_student_order_boundaries(): void
    {
        $school = $this->school('Own');
        $other = $this->school('Other');
        $own = Student::query()->create(['school_id' => $school->id]);
        $order = $this->order($own);
        $url = '/api/mobile/v1/orders/'.$order->id;
        $this->login($school);
        foreach ([$other->id, null] as $schoolId) {
            $foreign = Student::query()->create(['school_id' => $schoolId]);
            $foreignOrder = $this->order($foreign);
            $this->deleteJson('/api/mobile/v1/orders/'.$foreignOrder->id)->assertNotFound();
            $this->assertNotSoftDeleted($foreignOrder);
        }
        $this->deleteJson('/api/mobile/v1/orders/999999')->assertNotFound();
        $this->assertNotSoftDeleted($order);
        $own->delete();
        $this->deleteJson($url)->assertNoContent();
        $this->assertSoftDeleted($order);
    }
}

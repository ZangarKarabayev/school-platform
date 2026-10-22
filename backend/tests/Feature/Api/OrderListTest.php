<?php

namespace Tests\Feature\Api;

use App\Models\AcademicClass;
use App\Models\Dish;
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

class OrderListTest extends TestCase
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

    public function test_lists_are_school_scoped_and_student_path_cannot_be_overridden(): void
    {
        $school = $this->school('Own');
        $other = $this->school('Other');
        $own = Student::query()->create(['school_id' => $school->id, 'first_name' => 'Own', 'iin' => '123456789012']);
        $peer = Student::query()->create(['school_id' => $school->id]);
        $foreign = Student::query()->create(['school_id' => $other->id]);
        $unassigned = Student::query()->create(['school_id' => null]);
        $order = $this->order($own, data: ['transaction_error' => 'Private upstream payload']);
        foreach ([$peer, $foreign, $unassigned] as $student) {
            $this->order($student);
        }
        $this->login($school, scope: true);
        $this->getJson('/api/mobile/v1/orders?school_id='.$other->id)->assertOk()->assertJsonCount(2, 'orders');
        $this->getJson('/api/mobile/v1/students/'.$own->id.'/orders')->assertOk()->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $order->id)->assertJsonPath('orders.0.student.full_name', 'Own')
            ->assertJsonMissingPath('orders.0.student.iin')->assertJsonMissingPath('orders.0.student.benefit')
            ->assertJsonMissingPath('orders.0.transaction_error');
        $this->getJson('/api/mobile/v1/students/'.$own->id.'/orders?student_id='.$peer->id)->assertOk()->assertJsonCount(0, 'orders');
        foreach ([$foreign->id, $unassigned->id, 99999] as $id) {
            $this->getJson('/api/mobile/v1/students/'.$id.'/orders')->assertNotFound();
        }
        $this->getJson('/api/mobile/v1/orders?student_id='.$foreign->id)->assertOk()->assertJsonCount(0, 'orders');
    }

    public function test_filters_use_historical_class_and_preserve_false_transaction_status(): void
    {
        $school = $this->school('Own');
        $oldClass = AcademicClass::query()->create(['grade' => 3, 'letter' => 'A']);
        $newClass = AcademicClass::query()->create(['grade' => 4, 'letter' => 'A']);
        $student = Student::query()->create(['school_id' => $school->id, 'classroom_id' => $newClass->id, 'first_name' => 'Dana', 'last_name' => 'Student']);
        $dish = Dish::query()->create(['name' => 'Soup', 'category' => 'first', 'is_active' => true]);
        $expected = $this->order($student, '2026-10-20', ['classroom_id' => $oldClass->id, 'school_year' => '2026-2027', 'status' => 'failed', 'transaction_status' => false, 'dish_id' => $dish->id]);
        $this->order($student, '2026-10-21', ['classroom_id' => $newClass->id, 'status' => 'completed', 'transaction_status' => true]);
        $this->login($school);
        $query = '?date_from=2026-10-19&date_to=2026-10-20&classroom_id='.$oldClass->id.'&school_year=2026-2027&status=failed&transaction_status=0&search=Dana%20Student';
        foreach (['/orders', '/students/'.$student->id.'/orders'] as $path) {
            $this->getJson('/api/mobile/v1'.$path.$query)->assertOk()->assertJsonCount(1, 'orders')
                ->assertJsonPath('orders.0.id', $expected->id)->assertJsonPath('orders.0.classroom_id', $oldClass->id)
                ->assertJsonPath('orders.0.transaction_status', false)->assertJsonPath('orders.0.dish.name', 'Soup');
        }
        $this->getJson('/api/mobile/v1/orders?order_date=2026-10-21')->assertOk()->assertJsonCount(1, 'orders')->assertJsonPath('orders.0.status', 'completed');
        $this->getJson('/api/mobile/v1/orders?date_to=2026-10-20')->assertOk()->assertJsonCount(1, 'orders');
    }

    public function test_pagination_is_stable_and_validated(): void
    {
        $school = $this->school('Own');
        $student = Student::query()->create(['school_id' => $school->id]);
        $this->order($student, '2026-10-19');
        $last = $this->order($student, '2026-10-20');
        $this->login($school);
        $this->getJson('/api/mobile/v1/orders?per_page=1')->assertOk()->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $last->id)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/mobile/v1/orders?per_page=1&page=2')->assertOk()->assertJsonPath('orders.0.order_date', '2026-10-19');
        foreach (['per_page=101', 'page=0', 'status=unknown', 'transaction_status=wrong', 'order_date=wrong', 'date_from=2026-10-22&date_to=2026-10-20', 'school_year=2026-2028'] as $query) {
            $this->getJson('/api/mobile/v1/orders?'.$query)->assertUnprocessable();
        }
    }

    public function test_access_requires_teacher_permission_and_assignment(): void
    {
        $school = $this->school('Own');
        $student = Student::query()->create(['school_id' => $school->id]);
        $paths = ['/api/mobile/v1/orders', '/api/mobile/v1/students/'.$student->id.'/orders'];
        foreach ($paths as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        foreach ([[null, 'teacher'], [$school, 'director'], [$school, 'super_admin']] as [$assignment, $role]) {
            $this->login($assignment, $role);
            foreach ($paths as $path) {
                $this->getJson($path)->assertForbidden();
            }
        }
        $user = $this->login($school);
        $user->roles()->first()->permissions()->detach();
        app('auth')->forgetGuards();
        foreach ($paths as $path) {
            $this->getJson($path)->assertForbidden();
        }
    }

    public function test_student_without_orders_has_an_empty_paginated_list(): void
    {
        $school = $this->school('Own');
        $student = Student::query()->create(['school_id' => $school->id]);
        $this->login($school);
        $this->getJson('/api/mobile/v1/students/'.$student->id.'/orders')->assertOk()->assertJsonCount(0, 'orders')->assertJsonPath('meta.total', 0);
    }
}

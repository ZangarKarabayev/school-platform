<?php

namespace Tests\Feature\Web;

use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_orders_can_be_deleted_in_bulk(): void
    {
        $user = User::factory()->create();
        $student = Student::query()->create(['first_name' => 'Student']);
        $firstOrder = $this->createOrder($student);
        $secondOrder = $this->createOrder($student);
        $untouchedOrder = $this->createOrder($student);

        $this->actingAs($user)
            ->post(route('orders.bulk-destroy'), [
                'order_ids' => [$firstOrder->id, $secondOrder->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('order_status', __('ui.orders.bulk_deleted', ['count' => 2]));

        $this->assertDatabaseMissing('orders', ['id' => $firstOrder->id]);
        $this->assertDatabaseMissing('orders', ['id' => $secondOrder->id]);
        $this->assertDatabaseHas('orders', ['id' => $untouchedOrder->id]);
    }

    public function test_school_user_cannot_bulk_delete_an_order_from_another_school(): void
    {
        $region = Region::query()->create(['name_ru' => 'Region', 'code' => 'region']);
        $district = District::query()->create([
            'region_id' => $region->id,
            'name_ru' => 'District',
            'code' => 'district',
        ]);
        $firstSchool = School::query()->create([
            'district_id' => $district->id,
            'name_ru' => 'First school',
            'code' => 'first-school',
        ]);
        $secondSchool = School::query()->create([
            'district_id' => $district->id,
            'name_ru' => 'Second school',
            'code' => 'second-school',
        ]);
        $teacherRole = Role::query()->create([
            'code' => 'teacher',
            'name' => 'Teacher',
            'is_system' => true,
        ]);
        $teacher = User::factory()->create(['school_id' => $firstSchool->id]);
        $teacher->roles()->attach($teacherRole);
        $ownStudent = Student::query()->create([
            'school_id' => $firstSchool->id,
            'first_name' => 'Own',
        ]);
        $otherStudent = Student::query()->create([
            'school_id' => $secondSchool->id,
            'first_name' => 'Other',
        ]);
        $ownOrder = $this->createOrder($ownStudent);
        $otherOrder = $this->createOrder($otherStudent);

        $this->actingAs($teacher)
            ->post(route('orders.bulk-destroy'), [
                'order_ids' => [$ownOrder->id, $otherOrder->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $ownOrder->id]);
        $this->assertDatabaseHas('orders', ['id' => $otherOrder->id]);
    }

    private function createOrder(Student $student): Order
    {
        return Order::query()->create([
            'student_id' => $student->id,
            'order_date' => '2026-09-'.str_pad((string) (Order::query()->count() + 1), 2, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_CREATED,
        ]);
    }
}

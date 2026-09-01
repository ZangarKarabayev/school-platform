<?php

namespace Tests\Feature\Web;

use App\Jobs\CreateOrdersJob;
use App\Models\AcademicClass;
use App\Models\MealBenefit;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use App\Services\FaceIDEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderIndexSchoolYearFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_from_all_school_years_are_shown_by_default(): void
    {
        $user = User::factory()->create();
        $classroom = AcademicClass::query()->create(['grade' => 1, 'letter' => 'А']);
        $currentStudent = Student::query()->create([
            'first_name' => 'Current',
            'classroom_id' => $classroom->id,
            'school_year' => '2026-2027',
        ]);
        $legacyStudent = Student::query()->create(['first_name' => 'Legacy']);

        Order::query()->create([
            'student_id' => $currentStudent->id,
            'school_year' => '2026-2027',
            'order_date' => '2026-09-01',
            'status' => Order::STATUS_CREATED,
        ]);
        Order::query()->create([
            'student_id' => $legacyStudent->id,
            'school_year' => null,
            'order_date' => '2026-09-01',
            'status' => Order::STATUS_CREATED,
        ]);

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertViewHas('orders', fn($orders): bool => $orders->total() === 2)
            ->assertViewHas('selectedSchoolYear', null)
            ->assertViewHas('creationSchoolYear', '2026-2027');

        $this->actingAs($user)
            ->get(route('orders.index', ['school_year' => '2026-2027']))
            ->assertOk()
            ->assertViewHas('orders', fn($orders): bool => $orders->total() === 1);
    }

    public function test_create_orders_job_fills_current_school_year_and_classroom_when_missing(): void
    {
        $classroom = AcademicClass::query()->create(['grade' => 1, 'letter' => 'А']);
        $student = Student::query()->create([
            'first_name' => 'Auto',
            'school_year' => null,
            'classroom_id' => $classroom->id,
        ]);
        MealBenefit::query()->create([
            'student_id' => $student->id,
            'type' => 'voucher',
            'start_date' => '2026-09-01',
            'end_date' => '2027-05-31',
        ]);

        (new CreateOrdersJob([$student->id], '2026-09-01', null, null, null))->handle();

        $order = Order::query()->first();

        $this->assertNotNull($order);
        $this->assertSame('2026-2027', $order->school_year);
        $this->assertSame($classroom->id, $order->classroom_id);
    }

    public function test_face_id_event_fills_current_school_year_and_classroom_when_missing(): void
    {
        $region = Region::query()->create([
            'name' => 'Test Region',
            'name_ru' => 'Test Region',
            'code' => 'TR-01',
        ]);
        $district = District::query()->create([
            'region_id' => $region->id,
            'name' => 'Test District',
            'name_ru' => 'Test District',
            'code' => 'TD-01',
        ]);
        $school = School::query()->create([
            'district_id' => $district->id,
            'name' => 'Test School',
            'name_ru' => 'Test School',
            'code' => 'TS-01',
            'bin' => '123456789',
            'address' => 'Test Address',
        ]);

        $classroom = AcademicClass::query()->create(['grade' => 1, 'letter' => 'А']);
        $student = Student::query()->create([
            'first_name' => 'Mqtt',
            'school_id' => $school->id,
            'school_year' => null,
            'classroom_id' => $classroom->id,
        ]);
        MealBenefit::query()->create([
            'student_id' => $student->id,
            'type' => 'voucher',
            'start_date' => '2026-09-01',
            'end_date' => '2027-05-31',
        ]);

        FaceIDEventService::handle([
            'operator' => 'VerifyPush',
            'info' => [
                'DeviceID' => 42,
                'Notes' => (string) $student->id,
                'CreateTime' => '2026-09-01 12:00:00',
                'Name' => 'Mqtt Student',
                'Address' => '',
            ],
        ]);

        $order = Order::query()->first();

        $this->assertNotNull($order);
        $this->assertSame('2026-2027', $order->school_year);
        $this->assertSame($classroom->id, $order->classroom_id);
    }
}

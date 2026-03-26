<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CreateOrdersJob;
use App\Models\MealBenefit;
use App\Models\Order;
use App\Models\Student;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrdersJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_orders_only_for_students_with_eligible_benefit(): void
    {
        $school = $this->makeSchool();

        $eligibleStudent = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Ivan',
            'last_name' => 'Ivanov',
            'iin' => '123456789012',
            'status' => 'active',
        ]);

        MealBenefit::query()->create([
            'student_id' => $eligibleStudent->id,
            'type' => 'susn',
        ]);

        $ineligibleStudent = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Petr',
            'last_name' => 'Petrov',
            'iin' => '123456789013',
            'status' => 'active',
        ]);

        MealBenefit::query()->create([
            'student_id' => $ineligibleStudent->id,
            'type' => 'paid',
        ]);

        (new CreateOrdersJob(
            [$eligibleStudent->id, $ineligibleStudent->id],
            now()->toDateString(),
            '12:30'
        ))->handle();

        $this->assertDatabaseCount('orders', 1);
        $this->assertTrue(Order::query()->where('student_id', $eligibleStudent->id)->exists());
        $this->assertFalse(Order::query()->where('student_id', $ineligibleStudent->id)->exists());
    }

    private function makeSchool(): School
    {
        $region = Region::query()->create([
            'name' => 'Region',
            'name_ru' => 'Region',
            'name_kk' => 'Region',
            'code' => 'reg-1',
        ]);

        $district = District::query()->create([
            'region_id' => $region->id,
            'name' => 'District',
            'name_ru' => 'District',
            'name_kk' => 'District',
            'code' => 'dist-1',
        ]);

        return School::query()->create([
            'district_id' => $district->id,
            'name' => 'School A',
            'name_ru' => 'School A',
            'name_kk' => 'School A',
            'code' => 'school-a',
            'bin' => '111111111111',
            'address' => 'Address A',
            'is_active' => true,
        ]);
    }
}

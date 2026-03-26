<?php

namespace Tests\Feature\Web;

use App\Models\MealBenefit;
use App\Models\Order;
use App\Models\Student;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KitchenAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_qr_opens_kitchen_without_login(): void
    {
        [$school] = $this->makeSchools();

        $this->get(route('kitchen.access', $school->kitchen_access_token))
            ->assertOk()
            ->assertSee('QR-');
    }

    public function test_kitchen_without_token_shows_get_token_message(): void
    {
        $this->get(route('kitchen.index'))
            ->assertOk()
            ->assertSee('/kitchen/{token}', false);
    }

    public function test_kitchen_scan_creates_only_one_order_per_student_per_day(): void
    {
        [$school] = $this->makeSchools();

        $student = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Ivan',
            'last_name' => 'Ivanov',
            'iin' => '123456789012',
            'status' => 'active',
        ]);

        MealBenefit::query()->create([
            'student_id' => $student->id,
            'type' => 'susn',
        ]);

        $this->withSession(['kitchen_school_token' => $school->kitchen_access_token])
            ->postJson(route('kitchen.scan'), ['student_code' => 'student:'.$student->id])
            ->assertOk()
            ->assertJsonPath('created', true);

        $this->withSession(['kitchen_school_token' => $school->kitchen_access_token])
            ->postJson(route('kitchen.scan'), ['student_code' => 'student:'.$student->id])
            ->assertOk()
            ->assertJsonPath('created', false);

        $this->assertDatabaseCount('orders', 1);
        $this->assertTrue(
            Order::query()
                ->where('student_id', $student->id)
                ->whereDate('order_date', now()->toDateString())
                ->exists()
        );
    }

    public function test_kitchen_scan_rejects_student_without_eligible_benefit(): void
    {
        [$school] = $this->makeSchools();

        $student = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Ivan',
            'last_name' => 'Ivanov',
            'iin' => '123456789012',
            'status' => 'active',
        ]);

        MealBenefit::query()->create([
            'student_id' => $student->id,
            'type' => 'paid',
        ]);

        $response = $this->withSession(['kitchen_school_token' => $school->kitchen_access_token])
            ->postJson(route('kitchen.scan'), ['student_code' => 'student:'.$student->id]);

        $response->assertStatus(422);
        $this->assertSame('?????? ?? ??????? ?? ??? ???????????.', $response->json('message'));

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_kitchen_scan_requires_school_token_in_session(): void
    {
        [$school] = $this->makeSchools();

        $student = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Ivan',
            'last_name' => 'Ivanov',
            'iin' => '123456789012',
            'status' => 'active',
        ]);

        $this->postJson(route('kitchen.scan'), ['student_code' => 'student:'.$student->id])
            ->assertForbidden();
    }

    /**
     * @return array<int, School>
     */
    private function makeSchools(): array
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

        $schoolA = School::query()->create([
            'district_id' => $district->id,
            'name' => 'School A',
            'name_ru' => 'School A',
            'name_kk' => 'School A',
            'code' => 'school-a',
            'bin' => '111111111111',
            'address' => 'Address A',
            'kitchen_access_token' => Str::random(40),
            'is_active' => true,
        ]);

        $schoolB = School::query()->create([
            'district_id' => $district->id,
            'name' => 'School B',
            'name_ru' => 'School B',
            'name_kk' => 'School B',
            'code' => 'school-b',
            'bin' => '222222222222',
            'address' => 'Address B',
            'kitchen_access_token' => Str::random(40),
            'is_active' => true,
        ]);

        return [$schoolA, $schoolB];
    }
}
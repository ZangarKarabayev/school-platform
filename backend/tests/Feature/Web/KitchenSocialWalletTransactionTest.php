<?php

namespace Tests\Feature\Web;

use App\Jobs\SendSocialWalletTransactionJob;
use App\Models\MealBenefit;
use App\Models\Order;
use App\Models\Student;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class KitchenSocialWalletTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_scan_dispatches_social_wallet_transaction_job(): void
    {
        Carbon::setTestNow('2026-03-29 12:00:00');

        Queue::fake();

        $school = $this->makeSchool();

        $student = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Ivan',
            'last_name' => 'Ivanov',
            'iin' => '123456789012',
            'status' => 'active',
        ]);

        MealBenefit::query()->create([
            'student_id' => $student->id,
            'type' => 'voucher',
        ]);

        $response = $this->withSession(['kitchen_school_token' => $school->kitchen_access_token])
            ->postJson(route('kitchen.scan'), ['student_code' => 'student:'.$student->id]);

        $response
            ->assertOk()
            ->assertJsonPath('created', true)
            ->assertJsonPath('order.transaction_status', null)
            ->assertJsonPath('order.transaction_error', null);

        $order = Order::query()->sole();

        Queue::assertPushed(SendSocialWalletTransactionJob::class, function (SendSocialWalletTransactionJob $job) use ($order): bool {
            return $job->orderId === $order->id;
        });

        $this->assertSame(Order::STATUS_CREATED, $order->status);
        $this->assertNull($order->transaction_status);
        $this->assertNull($order->transaction_error);

        Carbon::setTestNow();
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
            'kitchen_access_token' => Str::random(40),
            'is_active' => true,
        ]);
    }
}

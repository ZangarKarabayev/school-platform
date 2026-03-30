<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendSocialWalletTransactionJob;
use App\Models\Order;
use App\Models\Student;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendSocialWalletTransactionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_transaction_and_updates_order_status(): void
    {
        config()->set('services.social_wallet.base_url', 'https://api.dev.socialwallet.kz');
        config()->set('services.social_wallet.username', 'demo');
        config()->set('services.social_wallet.password', 'secret');

        $school = $this->makeSchool();

        $student = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Ivan',
            'last_name' => 'Ivanov',
            'iin' => '123456789012',
            'status' => 'active',
        ]);

        $order = Order::query()->create([
            'student_id' => $student->id,
            'order_date' => '2026-03-29',
            'order_time' => '12:00:00',
            'status' => 'created',
            'transaction_status' => null,
            'transaction_error' => null,
        ]);

        Http::fake([
            'https://api.dev.socialwallet.kz/api/v1/sdu/meal/transaction' => Http::response([
                'success' => true,
            ], 200),
        ]);

        (new SendSocialWalletTransactionJob($order->id))->handle(app(\App\Services\SocialWallet\SocialWalletService::class));

        $order->refresh();

        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertTrue($order->transaction_status);
        $this->assertNull($order->transaction_error);
    }

    public function test_it_marks_order_as_failed_when_social_wallet_rejects_transaction(): void
    {
        config()->set('services.social_wallet.base_url', 'https://api.dev.socialwallet.kz');
        config()->set('services.social_wallet.username', 'demo');
        config()->set('services.social_wallet.password', 'secret');

        $school = $this->makeSchool();

        $student = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Ivan',
            'last_name' => 'Ivanov',
            'iin' => '123456789012',
            'status' => 'active',
        ]);

        $order = Order::query()->create([
            'student_id' => $student->id,
            'order_date' => '2026-03-29',
            'order_time' => '12:00:00',
            'status' => Order::STATUS_CREATED,
            'transaction_status' => null,
            'transaction_error' => null,
        ]);

        Http::fake([
            'https://api.dev.socialwallet.kz/api/v1/sdu/meal/transaction' => Http::response([
                'success' => false,
                'error_code' => 404,
                'error_msg' => 'iin не найден',
            ], 404),
        ]);

        (new SendSocialWalletTransactionJob($order->id))->handle(app(\App\Services\SocialWallet\SocialWalletService::class));

        $order->refresh();

        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertFalse($order->transaction_status);
        $this->assertSame('[404] iin не найден', $order->transaction_error);
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

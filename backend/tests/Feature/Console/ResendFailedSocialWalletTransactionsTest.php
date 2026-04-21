<?php

namespace Tests\Feature\Console;

use App\Jobs\SendSocialWalletTransactionJob;
use App\Models\Order;
use App\Models\Student;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResendFailedSocialWalletTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_only_failed_social_wallet_transactions(): void
    {
        Queue::fake();

        $school = $this->makeSchool();
        $student = $this->makeStudent($school);

        $failedOrder = $this->makeOrder($student, '2026-04-01', false);
        $this->makeOrder($student, '2026-04-02', true);
        $this->makeOrder($student, '2026-04-03', null);
        $this->makeOrder($student, '2026-04-04', false, null);

        $this->artisan('social-wallet:resend-failed-transactions')
            ->expectsOutput('1 failed Social Wallet transaction(s) queued for resend.')
            ->assertExitCode(0);

        Queue::assertPushed(SendSocialWalletTransactionJob::class, 1);
        Queue::assertPushed(SendSocialWalletTransactionJob::class, function (SendSocialWalletTransactionJob $job) use ($failedOrder): bool {
            return $job->orderId === $failedOrder->id;
        });
    }

    public function test_it_can_limit_failed_transactions_by_school(): void
    {
        Queue::fake();

        $school = $this->makeSchool('school-a', '111111111111');
        $otherSchool = $this->makeSchool('school-b', '222222222222');

        $schoolOrder = $this->makeOrder($this->makeStudent($school, '123456789012'), '2026-04-01', false);
        $this->makeOrder($this->makeStudent($otherSchool, '123456789013'), '2026-04-01', false);

        $this->artisan('social-wallet:resend-failed-transactions', [
            '--school_id' => $school->id,
        ])->assertExitCode(0);

        Queue::assertPushed(SendSocialWalletTransactionJob::class, 1);
        Queue::assertPushed(SendSocialWalletTransactionJob::class, function (SendSocialWalletTransactionJob $job) use ($schoolOrder): bool {
            return $job->orderId === $schoolOrder->id;
        });
    }

    private function makeStudent(School $school, string $iin = '123456789012'): Student
    {
        return Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Ivan',
            'last_name' => 'Ivanov',
            'iin' => $iin,
            'status' => 'active',
        ]);
    }

    private function makeOrder(
        Student $student,
        string $date,
        ?bool $transactionStatus,
        ?string $transactionError = 'Previous error',
    ): Order {
        return Order::query()->create([
            'student_id' => $student->id,
            'order_date' => $date,
            'order_time' => '12:00:00',
            'status' => $transactionStatus === false ? Order::STATUS_FAILED : Order::STATUS_CREATED,
            'transaction_status' => $transactionStatus,
            'transaction_error' => $transactionStatus === false ? $transactionError : null,
        ]);
    }

    private function makeSchool(string $code = 'school-a', string $bin = '111111111111'): School
    {
        $region = Region::query()->create([
            'name' => 'Region '.$code,
            'name_ru' => 'Region '.$code,
            'name_kk' => 'Region '.$code,
            'code' => 'reg-'.$code,
        ]);

        $district = District::query()->create([
            'region_id' => $region->id,
            'name' => 'District '.$code,
            'name_ru' => 'District '.$code,
            'name_kk' => 'District '.$code,
            'code' => 'dist-'.$code,
        ]);

        return School::query()->create([
            'district_id' => $district->id,
            'name' => 'School '.$code,
            'name_ru' => 'School '.$code,
            'name_kk' => 'School '.$code,
            'code' => $code,
            'bin' => $bin,
            'address' => 'Address '.$code,
            'is_active' => true,
        ]);
    }
}

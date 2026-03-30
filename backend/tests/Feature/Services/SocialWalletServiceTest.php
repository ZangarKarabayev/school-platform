<?php

namespace Tests\Feature\Services;

use App\Models\MealBenefit;
use App\Models\Student;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use App\Services\SocialWallet\SocialWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_active_vouchers_and_updates_existing_voucher_records(): void
    {
        config()->set('services.social_wallet.base_url', 'https://api.dev.socialwallet.kz');
        config()->set('services.social_wallet.username', 'demo');
        config()->set('services.social_wallet.password', 'secret');

        $school = $this->makeSchool();

        $studentWithExistingVoucher = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Aida',
            'last_name' => 'Existing',
            'iin' => '123456789012',
            'status' => 'active',
        ]);

        $existingVoucher = MealBenefit::query()->create([
            'student_id' => $studentWithExistingVoucher->id,
            'type' => 'voucher',
            'voucher_update_datetime' => now()->subDay(),
        ]);

        $studentWithoutVoucher = Student::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Bota',
            'last_name' => 'Created',
            'iin' => '123456789013',
            'status' => 'active',
        ]);

        MealBenefit::query()->create([
            'student_id' => $studentWithoutVoucher->id,
            'type' => 'paid',
        ]);

        Http::fake([
            'https://api.dev.socialwallet.kz/api/v1/sdu/meal/voucher/list/active*' => function ($request) {
                $page = (int) ($request['page'] ?? 0);

                return $page === 0
                    ? Http::response([
                        'content' => ['123456789012'],
                        'last' => false,
                    ], 200)
                    : Http::response([
                        'content' => ['123456789013', '999999999999'],
                        'last' => true,
                    ], 200);
            },
        ]);

        $result = app(SocialWalletService::class)->syncActiveVouchersForSchool($school);

        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['matched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['unmatched']);

        $this->assertNotNull($existingVoucher->fresh()->voucher_update_datetime);
        $this->assertTrue(
            MealBenefit::query()
                ->where('student_id', $studentWithoutVoucher->id)
                ->where('type', 'voucher')
                ->exists()
        );
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

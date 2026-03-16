<?php

namespace App\Console\Commands;

use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\School;
use Illuminate\Console\Command;

class SeedSchoolsTestDataCommand extends Command
{
    protected $signature = 'schools:seed-test {--fresh : Clear test schools before seeding}';

    protected $description = 'Seed test schools';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            School::query()->where('code', 'like', 'TEST-SCH-%')->delete();
        }

        $districts = District::query()->orderBy('code')->limit(3)->get();

        if ($districts->count() < 3) {
            $this->components->error('Not enough districts found. Run `php artisan org:import-kato` first.');

            return self::FAILURE;
        }

        $payload = [
            [
                'district_id' => $districts[0]->id,
                'name_ru' => 'Тестовая школа №1',
                'name_kk' => 'Тест мектебі №1',
                'code' => 'TEST-SCH-001',
                'bin' => '123456789001',
                'address' => 'г. Семей, ул. Абая, 1',
                'is_active' => true,
            ],
            [
                'district_id' => $districts[1]->id,
                'name_ru' => 'Тестовая школа №2',
                'name_kk' => 'Тест мектебі №2',
                'code' => 'TEST-SCH-002',
                'bin' => '123456789002',
                'address' => 'г. Курчатов, ул. Школьная, 2',
                'is_active' => true,
            ],
            [
                'district_id' => $districts[2]->id,
                'name_ru' => 'Тестовая школа №3',
                'name_kk' => 'Тест мектебі №3',
                'code' => 'TEST-SCH-003',
                'bin' => '123456789003',
                'address' => 'Абайский район, с. Карааул, 3',
                'is_active' => true,
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($payload as $item) {
            $school = School::query()->firstOrNew([
                'code' => $item['code'],
            ]);

            $wasExisting = $school->exists;
            $school->fill($item);
            $school->save();

            if ($wasExisting) {
                $updated++;
            } else {
                $created++;
            }
        }

        $this->components->info('Schools test data seeded successfully.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Total test schools', School::query()->where('code', 'like', 'TEST-SCH-%')->count()],
            ]
        );

        return self::SUCCESS;
    }
}
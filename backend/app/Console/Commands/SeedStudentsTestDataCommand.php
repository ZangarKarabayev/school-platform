<?php

namespace App\Console\Commands;

use App\Models\AcademicClass;
use App\Models\MealBenefit;
use App\Models\Student;
use App\Modules\Organizations\Models\School;
use Illuminate\Console\Command;

class SeedStudentsTestDataCommand extends Command
{
    protected $signature = 'students:seed-test {--count=20 : Number of students to create} {--fresh : Clear students and meal benefits before seeding}';

    protected $description = 'Seed test students and meal benefits';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            MealBenefit::query()->delete();
            Student::query()->delete();
        }

        $count = max(1, (int) $this->option('count'));
        $classes = AcademicClass::query()->orderBy('grade')->get();
        $schools = School::query()->orderBy('id')->get();

        if ($classes->isEmpty()) {
            $this->components->error('No classrooms found. Run `php artisan classrooms:seed --fresh` first.');

            return self::FAILURE;
        }

        $firstNames = ['Айбек', 'Аружан', 'Нұрай', 'Дамир', 'Алихан', 'Томирис', 'Ернар', 'Жансая', 'Мадина', 'Санжар'];
        $lastNames = ['Ахметов', 'Серикова', 'Касымов', 'Омарова', 'Тлеубергенов', 'Жумабекова', 'Нургалиев', 'Иманова', 'Абдрахманов', 'Сулейменова'];
        $middleNames = ['Ерланович', 'Аскаровна', 'Нурланович', 'Бауыржановна', 'Серикович', 'Кайратовна'];
        $statuses = ['active', 'archived'];
        $languages = ['ru', 'kk'];
        $genders = ['male', 'female'];

        $created = 0;

        foreach (range(1, $count) as $index) {
            $student = Student::query()->create([
                'iin' => str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
                'first_name' => $firstNames[array_rand($firstNames)],
                'last_name' => $lastNames[array_rand($lastNames)],
                'middle_name' => $middleNames[array_rand($middleNames)],
                'birth_date' => now()->subYears(random_int(7, 17))->subDays(random_int(0, 365))->toDateString(),
                'gender' => $genders[array_rand($genders)],
                'classroom_id' => $classes->random()->id,
                'school_id' => $schools->isNotEmpty() ? $schools->random()->id : null,
                'phone' => '+7701' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
                'address' => 'Test address ' . $index,
                'photo' => null,
                'status' => $statuses[array_rand($statuses)],
                'student_number' => 'ST-' . now()->format('y') . '-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'language' => $languages[array_rand($languages)],
                'shift' => random_int(1, 2),
                'school_year' => '2025-2026',
            ]);

            MealBenefit::query()->create([
                'student_id' => $student->id,
                'type' => MealBenefit::TYPES[array_rand(MealBenefit::TYPES)],
                'voucher_update_datetime' => now()->subDays(random_int(0, 30)),
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
            ]);

            $created++;
        }

        $this->components->info('Students test data seeded successfully.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Students created', $created],
                ['Total students', Student::query()->count()],
                ['Total meal benefits', MealBenefit::query()->count()],
            ]
        );

        return self::SUCCESS;
    }
}

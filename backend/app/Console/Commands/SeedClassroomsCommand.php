<?php

namespace App\Console\Commands;

use App\Models\AcademicClass;
use Illuminate\Console\Command;

class SeedClassroomsCommand extends Command
{
    protected $signature = 'classrooms:seed {--fresh : Clear classrooms before seeding}';

    protected $description = 'Fill classrooms from grade 0 to 11 using the Kazakh alphabet';

    /**
     * @var list<string>
     */
    private array $letters = [
        'А', 'Ә', 'Б', 'В', 'Г', 'Ғ', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Қ', 'Л', 'М', 'Н', 'Ң', 'О', 'Ө',
        'П', 'Р', 'С', 'Т', 'У', 'Ұ', 'Ү', 'Ф', 'Х', 'Һ', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'І', 'Ь', 'Э', 'Ю', 'Я',
    ];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            AcademicClass::query()->delete();
        }

        $created = 0;
        $updated = 0;

        foreach (range(0, 11) as $grade) {
            foreach ($this->letters as $letter) {
                $fullName = $grade . $letter;

                $classroom = AcademicClass::query()->firstOrNew([
                    'full_name' => $fullName,
                ]);

                $wasExisting = $classroom->exists;
                $classroom->grade = $grade;
                $classroom->letter = $letter;
                $classroom->save();

                if ($wasExisting) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        }

        $this->components->info('Classrooms seeded successfully.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Alphabet letters', count($this->letters)],
                ['Grades', 12],
                ['Created', $created],
                ['Updated', $updated],
                ['Total classrooms', AcademicClass::query()->count()],
            ]
        );

        return self::SUCCESS;
    }
}
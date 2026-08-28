<?php

namespace Tests\Feature\Models;

use App\Models\AcademicClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentClassroomHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_the_initial_classroom_and_each_transfer(): void
    {
        $firstClassroom = AcademicClass::query()->create([
            'grade' => 1,
            'letter' => 'А',
        ]);
        $secondClassroom = AcademicClass::query()->create([
            'grade' => 2,
            'letter' => 'А',
        ]);

        $student = Student::query()->create([
            'first_name' => 'Иван',
            'classroom_id' => $firstClassroom->id,
        ]);

        $this->assertSame([$firstClassroom->id], $student->classroom_history);

        $student->update(['classroom_id' => $secondClassroom->id]);

        $this->assertSame(
            [$firstClassroom->id, $secondClassroom->id],
            $student->fresh()->classroom_history,
        );
    }

    public function test_it_does_not_duplicate_an_unchanged_classroom_and_keeps_history_after_graduation(): void
    {
        $classroom = AcademicClass::query()->create([
            'grade' => 11,
            'letter' => 'Б',
        ]);

        $student = Student::query()->create([
            'first_name' => 'Анна',
            'classroom_id' => $classroom->id,
        ]);

        $student->save();
        $student->update(['classroom_id' => null]);

        $this->assertSame([$classroom->id], $student->fresh()->classroom_history);
    }
}

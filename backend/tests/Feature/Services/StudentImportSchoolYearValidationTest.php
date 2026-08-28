<?php

namespace Tests\Feature\Services;

use App\Models\Student;
use App\Models\StudentImport;
use App\Services\Students\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentImportSchoolYearValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_rows_with_a_valid_school_year(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/students.csv', implode("\n", [
            'IIN,Class,School year',
            '070101123456,5A,2025-2026',
            '070101123457,5A,',
            '070101123458,5A,2025-2027',
        ]));

        $studentImport = StudentImport::query()->create([
            'disk' => 'local',
            'file_path' => 'imports/students.csv',
            'original_name' => 'students.csv',
        ]);

        app(StudentImportService::class)->process($studentImport);

        $studentImport->refresh();

        $this->assertSame(1, $studentImport->imported_count);
        $this->assertSame(2, $studentImport->skipped_count);
        $this->assertCount(2, $studentImport->error_rows);
        $this->assertDatabaseHas('students', [
            'iin' => '070101123456',
            'school_year' => '2025-2026',
        ]);
        $this->assertDatabaseMissing('students', ['iin' => '070101123457']);
        $this->assertDatabaseMissing('students', ['iin' => '070101123458']);
    }

    public function test_an_empty_imported_year_does_not_clear_an_existing_year(): void
    {
        Storage::fake('local');

        Student::query()->create([
            'iin' => '070101123459',
            'school_year' => '2025-2026',
        ]);

        Storage::disk('local')->put('imports/students.csv', implode("\n", [
            'IIN,Class,School year',
            '070101123459,5A,',
        ]));

        $studentImport = StudentImport::query()->create([
            'disk' => 'local',
            'file_path' => 'imports/students.csv',
            'original_name' => 'students.csv',
        ]);

        app(StudentImportService::class)->process($studentImport);

        $this->assertSame('2025-2026', Student::query()->where('iin', '070101123459')->value('school_year'));
        $this->assertSame(1, $studentImport->fresh()->skipped_count);
    }
}

<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateReportJob;
use App\Models\AcademicClass;
use App\Models\GeneratedReport;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class GenerateReportHistoricalClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_report_filters_by_the_order_classroom_after_student_promotion(): void
    {
        $gradeFour = AcademicClass::query()->create(['grade' => 4, 'letter' => 'H']);
        $gradeFive = AcademicClass::query()->create(['grade' => 5, 'letter' => 'H']);
        $student = Student::query()->create([
            'classroom_id' => $gradeFive->id,
            'school_year' => '2026-2027',
            'status' => 'active',
        ]);
        $historicalOrder = Order::query()->create([
            'student_id' => $student->id,
            'classroom_id' => $gradeFour->id,
            'school_year' => '2025-2026',
            'order_date' => '2026-05-20',
            'status' => Order::STATUS_CREATED,
        ]);
        $currentOrder = Order::query()->create([
            'student_id' => $student->id,
            'classroom_id' => $gradeFive->id,
            'school_year' => '2026-2027',
            'order_date' => '2026-09-10',
            'status' => Order::STATUS_CREATED,
        ]);
        $report = new GeneratedReport(['report_type' => GeneratedReport::TYPE_1_4]);
        $query = Order::query();
        $method = new ReflectionMethod(GenerateReportJob::class, 'applyOrderReportFilters');

        $method->invoke(new GenerateReportJob($report), $query, $report);

        $this->assertSame([$historicalOrder->id], $query->pluck('id')->all());
        $this->assertNotSame($currentOrder->id, $historicalOrder->id);
    }

    public function test_current_student_report_excludes_graduated_students(): void
    {
        $classroom = AcademicClass::query()->create(['grade' => 5, 'letter' => 'R']);
        $activeStudent = Student::query()->create([
            'classroom_id' => $classroom->id,
            'school_year' => '2026-2027',
            'status' => 'active',
        ]);
        Student::query()->create([
            'classroom_id' => $classroom->id,
            'school_year' => '2026-2027',
            'status' => 'graduated',
        ]);
        $report = new GeneratedReport(['report_type' => GeneratedReport::TYPE_5_11]);
        $query = Student::query();
        $method = new ReflectionMethod(GenerateReportJob::class, 'applyStudentReportFilters');

        $method->invoke(new GenerateReportJob($report), $query, $report);

        $this->assertSame([$activeStudent->id], $query->pluck('id')->all());
    }
}

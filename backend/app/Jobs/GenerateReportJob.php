<?php

namespace App\Jobs;

use App\Models\GeneratedReport;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public GeneratedReport $report)
    {
    }

    public function handle(): void
    {
        $report = $this->report->fresh(['school']);

        if ($report === null) {
            return;
        }

        try {
            $orders = Order::query()
                ->with(['student.classroom', 'student.latestMealBenefit', 'student.school'])
                ->whereBetween('order_date', [$report->date_from, $report->date_to])
                ->whereHas('student', function ($query) use ($report): void {
                    $query->when(
                        $report->school_id !== null,
                        fn ($studentQuery) => $studentQuery->where('school_id', $report->school_id)
                    );

                    match ($report->report_type) {
                        GeneratedReport::TYPE_1_4 => $query
                            ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [1, 4])),
                        GeneratedReport::TYPE_1_5_SUSN => $query
                            ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [1, 5]))
                            ->whereHas('latestMealBenefit', fn ($benefitQuery) => $benefitQuery->where('type', 'susn')),
                        GeneratedReport::TYPE_5_11 => $query
                            ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [5, 11])),
                        GeneratedReport::TYPE_5_11_SUSN => $query
                            ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [5, 11]))
                            ->whereHas('latestMealBenefit', fn ($benefitQuery) => $benefitQuery->where('type', 'susn')),
                        default => null,
                    };
                })
                ->orderBy('order_date')
                ->orderBy('id')
                ->get();

            $directory = 'reports';
            $filename = 'report-' . $report->id . '-' . now()->format('YmdHis') . '.csv';
            $filePath = $directory . '/' . $filename;
            $path = Storage::disk('local')->path($filePath);

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            $handle = fopen($path, 'wb');

            if ($handle === false) {
                throw new \RuntimeException('Unable to create report file.');
            }

            // Excel on Windows correctly detects UTF-8 CSV only with BOM.
            fwrite($handle, "\xEF\xBB\xBF");

            $this->writeCsvRow($handle, ['Тип отчета', $report->type_label]);
            $this->writeCsvRow($handle, ['Период', $report->date_from->format('Y-m-d') . ' - ' . $report->date_to->format('Y-m-d')]);
            $this->writeCsvRow($handle, ['Школа', $report->school?->display_name ?: 'Все школы']);
            $this->writeCsvRow($handle, ['Всего заказов', (string) $orders->count()]);
            $this->writeCsvRow($handle, ['Уникальных учеников', (string) $orders->pluck('student_id')->unique()->count()]);
            $this->writeCsvRow($handle, []);
            $this->writeCsvRow($handle, ['Дата', 'Класс', 'Ученик', 'ИИН', 'Льгота', 'Статус заказа', 'Статус транзакции']);

            foreach ($orders as $order) {
                $student = $order->student;

                $this->writeCsvRow($handle, [
                    optional($order->order_date)->format('Y-m-d'),
                    $student?->classroom?->full_name,
                    $student?->full_name,
                    $student?->iin,
                    $student?->latestMealBenefit?->type,
                    $order->status,
                    $order->transaction_status === null ? '' : ($order->transaction_status ? 'success' : 'failed'),
                ]);
            }

            fclose($handle);

            $report->update([
                'status' => GeneratedReport::STATUS_COMPLETED,
                'file_disk' => 'local',
                'file_path' => $filePath,
                'error_message' => null,
                'generated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $report->update([
                'status' => GeneratedReport::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Use semicolon for better Excel compatibility on RU/KZ Windows locales.
     *
     * @param resource $handle
     * @param array<int, mixed> $row
     */
    private function writeCsvRow($handle, array $row): void
    {
        fputcsv($handle, $row, ';');
    }
}

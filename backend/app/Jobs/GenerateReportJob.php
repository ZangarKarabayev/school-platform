<?php

namespace App\Jobs;

use App\Models\GeneratedReport;
use App\Models\Order;
use App\Models\Student;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public GeneratedReport $report) {}

    public function handle(): void
    {
        $report = $this->report->fresh(['school']);

        if ($report === null) {
            return;
        }

        try {
            $orders = Order::query()
                ->with(['student.classroom', 'student.latestMealBenefit', 'student.school', 'classroom'])
                ->whereBetween('order_date', [$report->date_from, $report->date_to])
                ->tap(fn ($query) => $this->applyOrderReportFilters($query, $report))
                ->orderBy('order_date')
                ->orderBy('id')
                ->get();

            $students = Student::query()
                ->with(['classroom', 'latestMealBenefit', 'school'])
                ->whereNotNull('classroom_id')
                ->tap(fn ($query) => $this->applyStudentReportFilters($query, $report))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('middle_name')
                ->orderBy('id')
                ->get();

            $directory = 'reports';
            $safeName = preg_replace('/[^\pL\pN\s\-\.]/u', '', $report->type_label);
            $safeName = trim(preg_replace('/\s+/', '_', $safeName));
            $filename = $safeName
                .'_'.$report->date_from->format('d.m.Y')
                .'-'.$report->date_to->format('d.m.Y')
                .'.xlsx';
            $filePath = $directory.'/'.$filename;
            $path = Storage::disk('local')->path($filePath);

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            $this->buildSchoolReportXlsx($path, $report, $orders, $students);

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

    private function writeDetailedCsv(string $path, GeneratedReport $report, Collection $orders): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create report file.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        $this->writeCsvRow($handle, ['Тип отчета', $report->type_label]);
        $this->writeCsvRow($handle, ['Период', $report->date_from->format('Y-m-d').' - '.$report->date_to->format('Y-m-d')]);
        $this->writeCsvRow($handle, ['Школа', $report->school?->display_name ?: 'Все школы']);
        $this->writeCsvRow($handle, ['Всего заказов', (string) $orders->count()]);
        $this->writeCsvRow($handle, ['Уникальных учеников', (string) $orders->pluck('student_id')->unique()->count()]);
        $this->writeCsvRow($handle, []);
        $this->writeCsvRow($handle, ['Дата', 'Время', 'Класс', 'Ученик', 'ИИН', 'Льгота', 'Статус заказа', 'Статус транзакции']);

        foreach ($orders as $order) {
            $student = $order->student;

            $this->writeCsvRow($handle, [
                optional($order->order_date)->format('d.m.Y'),
                $this->formatOrderTimeForCsv($order->order_time),
                $order->classroom?->full_name ?? $student?->classroom?->full_name,
                $student?->full_name,
                $this->formatIinForCsv($student?->iin),
                $student?->latestMealBenefit?->type,
                $this->localizeOrderStatus($order->status),
                $this->localizeTransactionStatus($order->transaction_status),
            ]);
        }

        fclose($handle);
    }

    private function buildSchoolReportXlsx(
        string $path,
        GeneratedReport $report,
        Collection $orders,
        Collection $students
    ): void {
        $days = collect(CarbonPeriod::create($report->date_from, $report->date_to))
            ->map(fn ($date) => $date->copy())
            ->values();

        $periodLabel = $report->date_from->format('d.m.Y').' по '.$report->date_to->format('d.m.Y');
        $schoolName = $report->school?->display_name ?: 'Все школы';
        $typeLabel = $report->type_label;

        $styleBold = (new Style)->setFontBold();
        $styleCenter = (new Style)->setCellAlignment(CellAlignment::CENTER);
        $styleHeader = (new Style)
            ->setFontBold()
            ->setBackgroundColor('D9E1F2')
            ->setCellAlignment(CellAlignment::CENTER);
        $styleTotalRow = (new Style)
            ->setFontBold()
            ->setBackgroundColor('F2F2F2')
            ->setCellAlignment(CellAlignment::CENTER);

        $studentDatesMap = $orders
            ->groupBy('student_id')
            ->map(function (Collection $studentOrders): Collection {
                return $studentOrders
                    ->pluck('order_date')
                    ->filter()
                    ->map(fn ($date) => $date->format('Y-m-d'))
                    ->unique()
                    ->flip()
                    ->map(fn () => true);
            });

        $byClass = $students
            ->groupBy(fn (Student $student) => $student->classroom?->full_name ?? '-')
            ->map(function (Collection $classStudents) use ($studentDatesMap): Collection {
                return $classStudents
                    ->map(function (Student $student) use ($studentDatesMap): array {
                        $dates = $studentDatesMap->get($student->id, collect());

                        return [
                            'student' => $student,
                            'sort_key' => mb_strtoupper((string) $student->full_name),
                            'name' => $student->full_name ?: '-',
                            'dates' => $dates,
                            'total' => $dates->count(),
                        ];
                    })
                    ->sortBy('sort_key')
                    ->values();
            });

        $byClass = $byClass->sortKeysUsing(function (string $a, string $b): int {
            preg_match('/^(\d+)(.*)$/', $a, $ma);
            preg_match('/^(\d+)(.*)$/', $b, $mb);
            $gradeA = (int) ($ma[1] ?? 999);
            $gradeB = (int) ($mb[1] ?? 999);

            if ($gradeA !== $gradeB) {
                return $gradeA <=> $gradeB;
            }

            return mb_strtolower($ma[2] ?? '') <=> mb_strtolower($mb[2] ?? '');
        });

        $writer = new Writer;
        $writer->openToFile($path);

        $summarySheet = $writer->getCurrentSheet();
        $summarySheet->setName('Сводка');
        $summarySheet->setColumnWidth(6, 1);
        $summarySheet->setColumnWidth(14, 2);
        foreach (range(3, 2 + $days->count()) as $col) {
            $summarySheet->setColumnWidth(5, $col);
        }
        $summarySheet->setColumnWidth(8, 2 + $days->count() + 1);

        $writer->addRow(Row::fromValues(['', '', '', 'Утверждаю'], $styleBold));
        $writer->addRow(Row::fromValues(['', '', '', 'Директор________________'], $styleBold));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([$typeLabel.' с '.$periodLabel], $styleBold));
        $writer->addRow(Row::fromValues([$schoolName]));
        $writer->addRow(Row::fromValues([]));

        $headerValues = ['№', 'Класс'];
        foreach ($days as $date) {
            $headerValues[] = (int) $date->format('d');
        }
        $headerValues[] = 'ИТОГО';
        $writer->addRow(Row::fromValues($headerValues, $styleHeader));

        $grandTotal = 0;
        $dayColTotals = [];
        $rowIndex = 1;

        foreach ($byClass as $className => $classStudents) {
            $classTotal = 0;
            $dayCounts = [];

            foreach ($days as $date) {
                $key = $date->format('Y-m-d');
                $count = $classStudents->sum(fn (array $row): int => isset($row['dates'][$key]) ? 1 : 0);
                $dayCounts[$key] = $count;
                $dayColTotals[$key] = ($dayColTotals[$key] ?? 0) + $count;
                $classTotal += $count;
            }

            $cells = [
                Cell::fromValue($rowIndex++, $styleCenter),
                Cell::fromValue($className),
            ];

            foreach ($days as $date) {
                $cells[] = Cell::fromValue($dayCounts[$date->format('Y-m-d')], $styleCenter);
            }

            $cells[] = Cell::fromValue($classTotal, $styleCenter);
            $grandTotal += $classTotal;

            $writer->addRow(new Row($cells));
        }

        $totalsRow = ['', 'ИТОГО'];
        foreach ($days as $date) {
            $totalsRow[] = $dayColTotals[$date->format('Y-m-d')] ?? 0;
        }
        $totalsRow[] = $grandTotal;
        $writer->addRow(Row::fromValues($totalsRow, $styleTotalRow));

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Социальный педагог: ________________________________']));

        foreach ($byClass as $className => $classStudents) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(mb_substr($className, 0, 31));
            $sheet->setColumnWidth(6, 1);
            $sheet->setColumnWidth(36, 2);
            foreach (range(3, 2 + $days->count()) as $col) {
                $sheet->setColumnWidth(5, $col);
            }
            $sheet->setColumnWidth(8, 2 + $days->count() + 1);

            $writer->addRow(Row::fromValues(['', '', '', 'Утверждаю'], $styleBold));
            $writer->addRow(Row::fromValues(['', '', '', 'Директор________________'], $styleBold));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([$typeLabel.' с '.$periodLabel.' — '.$className], $styleBold));
            $writer->addRow(Row::fromValues([$schoolName]));
            $writer->addRow(Row::fromValues([]));

            $hdr = ['№', 'ФИО'];
            foreach ($days as $date) {
                $hdr[] = (int) $date->format('d');
            }
            $hdr[] = 'ИТОГО';
            $writer->addRow(Row::fromValues($hdr, $styleHeader));

            $classColTotals = [];
            $classGrandTotal = 0;

            foreach ($classStudents as $i => $row) {
                $cells = [
                    Cell::fromValue($i + 1, $styleCenter),
                    Cell::fromValue($row['name']),
                ];

                foreach ($days as $date) {
                    $key = $date->format('Y-m-d');
                    $has = isset($row['dates'][$key]);
                    $classColTotals[$key] = ($classColTotals[$key] ?? 0) + ($has ? 1 : 0);
                    $cells[] = Cell::fromValue($has ? 1 : 0, $styleCenter);
                }

                $cells[] = Cell::fromValue($row['total'], $styleCenter);
                $classGrandTotal += $row['total'];
                $writer->addRow(new Row($cells));
            }

            $tRow = ['', 'ИТОГО'];
            foreach ($days as $date) {
                $tRow[] = $classColTotals[$date->format('Y-m-d')] ?? 0;
            }
            $tRow[] = $classGrandTotal;
            $writer->addRow(Row::fromValues($tRow, $styleTotalRow));

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Социальный педагог: ________________________________']));
        }

        $writer->close();
    }

    private function applyStudentReportFilters($query, GeneratedReport $report)
    {
        $query->where('status', '!=', 'graduated');

        $query->when(
            $report->school_id !== null,
            fn ($studentQuery) => $studentQuery->where('school_id', $report->school_id)
        );

        match ($report->report_type) {
            GeneratedReport::TYPE_1_4 => $query
                ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [1, 4])),
            GeneratedReport::TYPE_1_4_SUSN => $query
                ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [1, 4]))
                ->whereHas('latestMealBenefit', fn ($benefitQuery) => $benefitQuery->where('type', 'susn')),
            GeneratedReport::TYPE_5_11 => $query
                ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [5, 11])),
            GeneratedReport::TYPE_5_11_SUSN => $query
                ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [5, 11]))
                ->whereHas('latestMealBenefit', fn ($benefitQuery) => $benefitQuery->where('type', 'susn')),
            default => $query,
        };

        return $query;
    }

    private function applyOrderReportFilters($query, GeneratedReport $report)
    {
        $query->when(
            $report->school_id !== null,
            fn ($orderQuery) => $orderQuery->whereHas(
                'student',
                fn ($studentQuery) => $studentQuery->where('school_id', $report->school_id),
            ),
        );

        match ($report->report_type) {
            GeneratedReport::TYPE_1_4 => $query
                ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [1, 4])),
            GeneratedReport::TYPE_1_4_SUSN => $query
                ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [1, 4]))
                ->whereHas('student.latestMealBenefit', fn ($benefitQuery) => $benefitQuery->where('type', 'susn')),
            GeneratedReport::TYPE_5_11 => $query
                ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [5, 11])),
            GeneratedReport::TYPE_5_11_SUSN => $query
                ->whereHas('classroom', fn ($classroomQuery) => $classroomQuery->whereBetween('grade', [5, 11]))
                ->whereHas('student.latestMealBenefit', fn ($benefitQuery) => $benefitQuery->where('type', 'susn')),
            default => $query,
        };

        return $query;
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    private function writeCsvRow($handle, array $row): void
    {
        fputcsv($handle, $row, ';');
    }

    private function formatIinForCsv(?string $iin): ?string
    {
        if ($iin === null || $iin === '') {
            return $iin;
        }

        return preg_match('/^\d{12}$/', $iin) ? "\t".$iin : $iin;
    }

    private function localizeOrderStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        $label = __('ui.orders.statuses.'.$status);

        return $label !== 'ui.orders.statuses.'.$status ? $label : $status;
    }

    private function localizeTransactionStatus(?bool $status): string
    {
        if ($status === null) {
            return '';
        }

        return $status
            ? __('ui.orders.transaction_result.success')
            : __('ui.orders.transaction_result.failed');
    }

    private function formatOrderTimeForCsv(?string $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        return substr($time, 0, 5);
    }
}

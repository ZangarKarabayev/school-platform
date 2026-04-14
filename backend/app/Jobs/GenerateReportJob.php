<?php

namespace App\Jobs;

use App\Models\GeneratedReport;
use App\Models\Order;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
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
            $extension = $report->report_type === GeneratedReport::TYPE_SCHOOL ? 'xls' : 'csv';
            $filename = 'report-' . $report->id . '-' . now()->format('YmdHis') . '.' . $extension;
            $filePath = $directory . '/' . $filename;
            $path = Storage::disk('local')->path($filePath);

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            if ($report->report_type === GeneratedReport::TYPE_SCHOOL) {
                file_put_contents($path, $this->buildSchoolReportHtml($report, $orders));
            } else {
                $this->writeDetailedCsv($path, $report, $orders);
            }

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
        $this->writeCsvRow($handle, ['Период', $report->date_from->format('Y-m-d') . ' - ' . $report->date_to->format('Y-m-d')]);
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
                $student?->classroom?->full_name,
                $student?->full_name,
                $this->formatIinForCsv($student?->iin),
                $student?->latestMealBenefit?->type,
                $this->localizeOrderStatus($order->status),
                $this->localizeTransactionStatus($order->transaction_status),
            ]);
        }

        fclose($handle);
    }

    private function buildSchoolReportHtml(GeneratedReport $report, Collection $orders): string
    {
        $days = collect(CarbonPeriod::create($report->date_from, $report->date_to))
            ->map(fn ($date) => $date->copy())
            ->values();

        $students = $orders
            ->groupBy('student_id')
            ->map(function (Collection $studentOrders): array {
                /** @var \App\Models\Order $firstOrder */
                $firstOrder = $studentOrders->first();
                $student = $firstOrder->student;

                $dailyCounts = $studentOrders
                    ->groupBy(fn ($order) => optional($order->order_date)->format('Y-m-d'))
                    ->map(fn (Collection $group): int => $group->count());

                return [
                    'student' => $student,
                    'class_name' => $student?->classroom?->full_name ?: '-',
                    'full_name' => $student?->full_name ?: '-',
                    'daily_counts' => $dailyCounts,
                    'total' => $dailyCounts->sum(),
                ];
            })
            ->sortBy(function (array $row): string {
                $student = $row['student'];
                $grade = str_pad((string) ($student?->classroom?->grade ?? 999), 3, '0', STR_PAD_LEFT);
                $letter = mb_strtoupper((string) ($student?->classroom?->letter ?? 'ZZZ'));
                $name = mb_strtoupper((string) $row['full_name']);

                return implode('|', [$grade, $letter, $name]);
            })
            ->values();

        $dayTotals = $days->mapWithKeys(function ($date) use ($students): array {
            $key = $date->format('Y-m-d');

            return [
                $key => $students->sum(fn (array $row): int => (int) ($row['daily_counts'][$key] ?? 0)),
            ];
        });

        $periodLabel = $report->date_from->format('d.m.Y') . ' - ' . $report->date_to->format('d.m.Y');
        $title = 'Табель учащихся с категорией с ' . $periodLabel;
        $schoolName = $report->school?->display_name ?: 'Все школы';

        $html = <<<'HTML'
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style>
table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11pt; }
td, th { border: 1px solid #000; padding: 4px 6px; }
.plain { border: none; }
.center { text-align: center; }
.right { text-align: right; }
.bold { font-weight: 700; }
.day { width: 34px; text-align: center; }
.name { min-width: 260px; }
.class { min-width: 72px; text-align: center; }
.num { width: 42px; text-align: center; }
.total { width: 56px; text-align: center; font-weight: 700; }
</style>
</head>
<body>
<table>
HTML;

        $columnCount = 4 + $days->count();

        $html .= '<tr>';
        $html .= '<td class="plain"></td><td class="plain"></td>';
        $html .= '<td class="plain right bold" colspan="' . max($columnCount - 2, 1) . '">Утверждаю</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td class="plain"></td><td class="plain"></td>';
        $html .= '<td class="plain right bold" colspan="' . max($columnCount - 2, 1) . '">Директор________________</td>';
        $html .= '</tr>';

        $html .= '<tr><td class="plain" colspan="' . $columnCount . '"></td></tr>';
        $html .= '<tr><td class="plain center bold" colspan="' . $columnCount . '">' . e($title) . '</td></tr>';
        $html .= '<tr><td class="plain center" colspan="' . $columnCount . '">' . e($schoolName) . '</td></tr>';
        $html .= '<tr><td class="plain" colspan="' . $columnCount . '"></td></tr>';

        $html .= '<tr>';
        $html .= '<th class="num">№</th>';
        $html .= '<th class="name">ФИО</th>';
        $html .= '<th class="class">Класс</th>';
        foreach ($days as $date) {
            $html .= '<th class="day">' . $date->format('d') . '</th>';
        }
        $html .= '<th class="total">ИТОГО</th>';
        $html .= '</tr>';

        foreach ($students as $index => $row) {
            $html .= '<tr>';
            $html .= '<td class="num">' . ($index + 1) . '</td>';
            $html .= '<td class="name">' . e($row['full_name']) . '</td>';
            $html .= '<td class="class">' . e($row['class_name']) . '</td>';
            foreach ($days as $date) {
                $key = $date->format('Y-m-d');
                $value = (int) ($row['daily_counts'][$key] ?? 0);
                $html .= '<td class="day">' . ($value > 0 ? $value : '') . '</td>';
            }
            $html .= '<td class="total">' . $row['total'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '<tr>';
        $html .= '<td class="plain" colspan="3"></td>';
        foreach ($days as $date) {
            $html .= '<td class="day bold">' . ((int) $dayTotals[$date->format('Y-m-d')] ?: '') . '</td>';
        }
        $html .= '<td class="total">' . $students->sum('total') . '</td>';
        $html .= '</tr>';

        $html .= '<tr><td class="plain" colspan="' . $columnCount . '"></td></tr>';
        $html .= '<tr><td class="plain" colspan="' . $columnCount . '"></td></tr>';
        $html .= '<tr>';
        $html .= '<td class="plain"></td>';
        $html .= '<td class="plain center" colspan="' . max($columnCount - 2, 1) . '">Социальный педагог: ________________________________</td>';
        $html .= '<td class="plain"></td>';
        $html .= '</tr>';

        $html .= '</table></body></html>';

        return $html;
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

    private function formatIinForCsv(?string $iin): ?string
    {
        if ($iin === null || $iin === '') {
            return $iin;
        }

        return preg_match('/^\d{12}$/', $iin) ? "\t" . $iin : $iin;
    }

    private function localizeOrderStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        $label = __('ui.orders.statuses.' . $status);

        return $label !== 'ui.orders.statuses.' . $status ? $label : $status;
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

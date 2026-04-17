<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardDataService $dashboardData): View
    {
        $data = $dashboardData->build($request->user(), [
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'scope_kind' => $request->string('scope_kind')->toString(),
            'school_id' => $request->integer('school_id') ?: null,
            'district_id' => $request->integer('district_id') ?: null,
            'region_id' => $request->integer('region_id') ?: null,
        ]);

        return view('dashboard', [
            'user' => $request->user(),
            'filters' => $data['filters'],
            'scopeConfig' => $data['scopeConfig'],
            'stats' => $data['stats'],
            'charts' => $data['charts'],
            'ordersTable' => $data['ordersTable'],
            'showOrdersTable' => true,
        ]);
    }

    public function exportOrdersTable(Request $request, DashboardDataService $dashboardData): BinaryFileResponse
    {
        $data = $dashboardData->build($request->user(), [
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'scope_kind' => $request->string('scope_kind')->toString(),
            'school_id' => $request->integer('school_id') ?: null,
            'district_id' => $request->integer('district_id') ?: null,
            'region_id' => $request->integer('region_id') ?: null,
        ]);

        $ordersTable = $data['ordersTable'];
        $tempPath = tempnam(sys_get_temp_dir(), 'dashboard-orders-');

        if ($tempPath === false) {
            abort(500, 'Unable to create export file.');
        }

        $xlsxPath = $tempPath . '.xlsx';
        @rename($tempPath, $xlsxPath);

        $headerStyle = (new Style())
            ->setFontBold()
            ->setBackgroundColor('DCE9F9')
            ->setCellAlignment(CellAlignment::CENTER);
        $dayHeaderStyle = (new Style())
            ->setFontBold()
            ->setBackgroundColor('DCE9F9')
            ->setCellAlignment(CellAlignment::CENTER)
            ->setTextRotation(90);
        $totalStyle = (new Style())
            ->setFontBold()
            ->setBackgroundColor('DCE9F9')
            ->setCellAlignment(CellAlignment::CENTER);
        $centerStyle = (new Style())
            ->setCellAlignment(CellAlignment::CENTER);
        $boldStyle = (new Style())
            ->setFontBold();

        $days = collect($ordersTable['days'] ?? []);
        $rows = collect($ordersTable['rows'] ?? []);
        $classSummaryRows = $this->buildClassSummaryRows($rows, $days);
        $scopeTitle = $this->resolveDashboardScopeTitle($data);

        $writer = new Writer();
        $writer->openToFile($xlsxPath);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(__('ui.dashboard_page.summary_sheet'));
        $sheet->setColumnWidth(4, 1);
        $sheet->setColumnWidth(10, 2);

        foreach (range(3, 2 + $days->count()) as $col) {
            $sheet->setColumnWidth(3.8, $col);
        }

        $sheet->setColumnWidth(6, 3 + $days->count());

        $writer->addRow(Row::fromValues(['', '', __('ui.dashboard_page.approve')], $boldStyle));
        $writer->addRow(Row::fromValues(['', '', __('ui.dashboard_page.director_line')], $boldStyle));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            __('ui.dashboard_page.orders_report_period', [
                'date_from' => $data['filters']['date_from'] ?? '',
                'date_to' => $data['filters']['date_to'] ?? '',
            ]),
        ], $boldStyle));
        $writer->addRow(Row::fromValues([$scopeTitle]));
        $writer->addRow(Row::fromValues([]));

        $headerCells = [
            Cell::fromValue('№', $headerStyle),
            Cell::fromValue(__('ui.dashboard_page.classroom'), $headerStyle),
        ];

        foreach ($days as $day) {
            $headerCells[] = Cell::fromValue((string) $day['label'], $dayHeaderStyle);
        }

        $headerCells[] = Cell::fromValue(__('ui.dashboard_page.total'), $headerStyle);
        $headerRow = new Row($headerCells);
        $headerRow->setHeight(42);
        $writer->addRow($headerRow);

        foreach ($classSummaryRows as $row) {
            $values = [
                $row['number'],
                $row['classroom_name'],
            ];

            foreach ($days as $day) {
                $values[] = $row['values'][$day['key']] ?: '';
            }

            $values[] = $row['total'];
            $writer->addRow(Row::fromValues($values, $centerStyle));
        }

        $totals = ['', __('ui.dashboard_page.total')];

        foreach ($days as $day) {
            $totals[] = $ordersTable['column_totals'][$day['key']] ?? 0;
        }

        $totals[] = $ordersTable['grand_total'] ?? 0;
        $writer->addRow(Row::fromValues($totals, $totalStyle));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([__('ui.dashboard_page.social_teacher_line')]));

        $rows
            ->groupBy(fn (array $row): string => (string) ($row['classroom_name'] ?? '-'))
            ->sortKeysUsing(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->each(function (Collection $classRows, string $className) use ($writer, $days, $boldStyle, $headerStyle, $dayHeaderStyle, $totalStyle, $centerStyle, $scopeTitle, $data): void {
                $sheet = $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName(mb_substr($className !== '' ? $className : __('ui.dashboard_page.no_classroom'), 0, 31));
                $sheet->setColumnWidth(4, 1);
                $sheet->setColumnWidth(26, 2);

                foreach (range(3, 2 + $days->count()) as $col) {
                    $sheet->setColumnWidth(3.8, $col);
                }

                $sheet->setColumnWidth(6, 3 + $days->count());

                $writer->addRow(Row::fromValues(['', '', __('ui.dashboard_page.approve')], $boldStyle));
                $writer->addRow(Row::fromValues(['', '', __('ui.dashboard_page.director_line')], $boldStyle));
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues([
                    __('ui.dashboard_page.orders_report_period_class', [
                        'date_from' => $data['filters']['date_from'] ?? '',
                        'date_to' => $data['filters']['date_to'] ?? '',
                        'classroom' => $className,
                    ]),
                ], $boldStyle));
                $writer->addRow(Row::fromValues([$scopeTitle]));
                $writer->addRow(Row::fromValues([]));

                $headerCells = [
                    Cell::fromValue('№', $headerStyle),
                    Cell::fromValue('ФИО', $headerStyle),
                ];

                foreach ($days as $day) {
                    $headerCells[] = Cell::fromValue((string) $day['label'], $dayHeaderStyle);
                }

                $headerCells[] = Cell::fromValue(__('ui.dashboard_page.total'), $headerStyle);
                $headerRow = new Row($headerCells);
                $headerRow->setHeight(42);
                $writer->addRow($headerRow);

                foreach ($classRows->values() as $index => $row) {
                    $values = [
                        $index + 1,
                        $row['full_name'],
                    ];

                    foreach ($days as $day) {
                        $values[] = $row['values'][$day['key']] ?: '';
                    }

                    $values[] = $row['total'];
                    $writer->addRow(Row::fromValues($values, $centerStyle));
                }

                $totals = ['', __('ui.dashboard_page.total')];

                foreach ($days as $day) {
                    $totals[] = $classRows->sum(fn (array $row): int => (int) ($row['values'][$day['key']] ?? 0));
                }

                $totals[] = $classRows->sum(fn (array $row): int => (int) ($row['total'] ?? 0));
                $writer->addRow(Row::fromValues($totals, $totalStyle));
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues([__('ui.dashboard_page.social_teacher_line')]));
            });
        $writer->close();

        $filename = 'dashboard-orders-' . Str::of((string) ($data['filters']['date_from'] ?? now()->toDateString()))->replace('-', '.')
            . '-' . Str::of((string) ($data['filters']['date_to'] ?? now()->toDateString()))->replace('-', '.') . '.xlsx';

        return response()->download($xlsxPath, (string) $filename)->deleteFileAfterSend(true);
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @param Collection<int, array<string, mixed>> $days
     * @return Collection<int, array<string, mixed>>
     */
    private function buildClassSummaryRows(Collection $rows, Collection $days): Collection
    {
        return $rows
            ->groupBy(fn (array $row): string => (string) ($row['classroom_name'] ?? '-'))
            ->sortKeysUsing(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->values()
            ->map(function (Collection $classRows, int $index) use ($days): array {
                $values = [];
                $total = 0;

                foreach ($days as $day) {
                    $dayTotal = $classRows->sum(fn (array $row): int => (int) ($row['values'][$day['key']] ?? 0));
                    $values[$day['key']] = $dayTotal;
                    $total += $dayTotal;
                }

                return [
                    'number' => $index + 1,
                    'classroom_name' => (string) ($classRows->first()['classroom_name'] ?? '-'),
                    'values' => $values,
                    'total' => $total,
                ];
            })
            ->values();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveDashboardScopeTitle(array $data): string
    {
        $filters = $data['filters'] ?? [];
        $scopeConfig = $data['scopeConfig'] ?? [];

        if (($filters['school_id'] ?? null) !== null) {
            $school = collect($scopeConfig['schools'] ?? [])->firstWhere('id', (int) $filters['school_id']);

            if ($school !== null) {
                return (string) ($school->display_name ?? __('ui.dashboard_page.scope_school_fallback'));
            }
        }

        if (($filters['district_id'] ?? null) !== null) {
            $district = collect($scopeConfig['districts'] ?? [])->firstWhere('id', (int) $filters['district_id']);

            if ($district !== null) {
                return (string) ($district->display_name ?? __('ui.dashboard_page.scope_district_fallback'));
            }
        }

        if (! empty($scopeConfig['schools']) && collect($scopeConfig['schools'])->count() === 1) {
            return (string) (collect($scopeConfig['schools'])->first()->display_name ?? __('ui.dashboard_page.scope_school_fallback'));
        }

        if (! empty($scopeConfig['districts']) && collect($scopeConfig['districts'])->count() === 1) {
            return (string) (collect($scopeConfig['districts'])->first()->display_name ?? __('ui.dashboard_page.scope_district_fallback'));
        }

        if (! empty($scopeConfig['regions']) && collect($scopeConfig['regions'])->count() === 1) {
            return (string) (collect($scopeConfig['regions'])->first()->display_name ?? __('ui.dashboard_page.scope_region_fallback'));
        }

        return __('ui.dashboard_page.summary_orders_table');
    }
}

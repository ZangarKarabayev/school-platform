<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardController extends Controller
{
    private const PDF_MEAL_PRICE = 100.00;

    public function __invoke(Request $request, DashboardDataService $dashboardData): View
    {
        $data = $this->buildDashboardData($request, $dashboardData);

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
        $data = $this->buildDashboardData($request, $dashboardData);

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

    public function exportOrdersTablePdf(Request $request, DashboardDataService $dashboardData): Response
    {
        $data = $this->buildDashboardData($request, $dashboardData);
        $pdfData = $this->buildOrdersTablePdfData($data);

        return Pdf::loadView('dashboard.orders-table-pdf', $pdfData)
            ->setPaper('a4', 'landscape')
            ->download($pdfData['filename']);
    }

    public function verifyOrdersTablePdf(Request $request): View
    {
        return view('dashboard.orders-table-pdf-verify', [
            'scopeTitle' => (string) $request->query('scope_title', ''),
            'dateFrom' => (string) $request->query('date_from', ''),
            'dateTo' => (string) $request->query('date_to', ''),
            'studentsCount' => (int) $request->query('students_count', 0),
            'grandTotal' => (int) $request->query('grand_total', 0),
            'mealPrice' => (float) $request->query('meal_price', self::PDF_MEAL_PRICE),
            'documentHash' => (string) $request->query('hash', ''),
        ]);
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

    private function buildDashboardData(Request $request, DashboardDataService $dashboardData): array
    {
        return $dashboardData->build($request->user(), [
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'scope_kind' => $request->string('scope_kind')->toString(),
            'school_id' => $request->integer('school_id') ?: null,
            'district_id' => $request->integer('district_id') ?: null,
            'region_id' => $request->integer('region_id') ?: null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildOrdersTablePdfData(array $data): array
    {
        $ordersTable = $data['ordersTable'] ?? [];
        $rows = collect($ordersTable['rows'] ?? []);
        $mealPrice = self::PDF_MEAL_PRICE;
        $scopeTitle = $this->resolveDashboardScopeTitle($data);
        $dateFrom = (string) ($data['filters']['date_from'] ?? '');
        $dateTo = (string) ($data['filters']['date_to'] ?? '');
        $grandTotal = (int) ($ordersTable['grand_total'] ?? 0);
        $studentsCount = $rows->count();
        $documentHash = strtoupper(substr(hash('sha256', implode('|', [
            $scopeTitle,
            $dateFrom,
            $dateTo,
            (string) $studentsCount,
            (string) $grandTotal,
            number_format($mealPrice, 2, '.', ''),
        ])), 0, 16));

        $verificationUrl = URL::signedRoute('dashboard.verify-orders-table-pdf', [
            'scope_title' => $scopeTitle,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'students_count' => $studentsCount,
            'grand_total' => $grandTotal,
            'meal_price' => number_format($mealPrice, 2, '.', ''),
            'hash' => $documentHash,
        ]);

        $qrSvg = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'scale' => 4,
        ])))->render($verificationUrl);

        return [
            'scopeTitle' => $scopeTitle,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'days' => $ordersTable['days'] ?? [],
            'rows' => $rows->map(function (array $row) use ($mealPrice): array {
                $daysTotal = (int) ($row['total'] ?? 0);

                return [
                    'number' => $row['number'] ?? '',
                    'full_name' => $row['full_name'] ?? '',
                    'classroom_name' => $row['classroom_name'] ?? '',
                    'class_label' => $this->resolvePdfClassLabel((string) ($row['classroom_name'] ?? '')),
                    'values' => $row['values'] ?? [],
                    'days_total' => $daysTotal,
                    'meal_price' => $mealPrice,
                    'amount_total' => $daysTotal * $mealPrice,
                ];
            })->all(),
            'studentsCount' => $studentsCount,
            'grandTotal' => $grandTotal,
            'grandAmount' => $grandTotal * $mealPrice,
            'mealPrice' => $mealPrice,
            'qrSvg' => $qrSvg,
            'documentHash' => $documentHash,
            'filename' => 'dashboard-orders-' . Str::of($dateFrom ?: now()->toDateString())->replace('-', '.')
                . '-' . Str::of($dateTo ?: now()->toDateString())->replace('-', '.') . '.pdf',
        ];
    }

    private function resolvePdfClassLabel(string $classroomName): string
    {
        if (preg_match('/^\d+/', trim($classroomName), $matches) === 1) {
            return $matches[0];
        }

        return $classroomName !== '' ? $classroomName : '-';
    }
}

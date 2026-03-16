<?php

namespace App\Services\Students;

use App\Models\AcademicClass;
use App\Models\MealBenefit;
use App\Models\Student;
use App\Models\StudentImport;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class StudentImportService
{
    public const STUDENT_IMPORT_HEADERS = [
        'iin' => ['iin', 'иин', 'жсн'],
        'last_name' => ['last_name', 'lastname', 'last name', 'фамилия', 'тегі'],
        'first_name' => ['first_name', 'firstname', 'first name', 'имя', 'аты'],
        'middle_name' => ['middle_name', 'middlename', 'middle name', 'отчество', 'әкесініңаты', 'әкесінің аты'],
        'classroom' => ['class', 'classroom', 'class_name', 'класс', 'сынып'],
        'language' => ['language', 'lang', 'язык', 'тіл'],
        'shift' => ['shift', 'смена', 'ауысым'],
        'status' => ['status', 'статус', 'мәртебе'],
        'student_number' => ['student_number', 'studentnumber', 'student number', 'номерученика', 'номер ученика', 'оқушынөмірі', 'оқушы нөмірі'],
        'phone' => ['phone', 'телефон'],
        'address' => ['address', 'адрес', 'мекенжай'],
        'school_year' => ['school_year', 'schoolyear', 'school year', 'учебныйгод', 'учебный год', 'оқужылы', 'оқу жылы'],
        'benefit_start_date' => ['benefit_start_date', 'start_date', 'датаначала', 'дата начала', 'басталукүні', 'басталу күні'],
        'benefit_end_date' => ['benefit_end_date', 'end_date', 'датаокончания', 'дата окончания', 'аяқталукүні', 'аяқталу күні'],
    ];

    public const STUDENT_IMPORT_TEMPLATE_HEADERS = [
        'IIN',
        "\u{0424}\u{0430}\u{043C}\u{0438}\u{043B}\u{0438}\u{044F}",
        "\u{0418}\u{043C}\u{044F}",
        "\u{041E}\u{0442}\u{0447}\u{0435}\u{0441}\u{0442}\u{0432}\u{043E}",
        "\u{041A}\u{043B}\u{0430}\u{0441}\u{0441}",
        "\u{042F}\u{0437}\u{044B}\u{043A}",
        "\u{0421}\u{043C}\u{0435}\u{043D}\u{0430}",
        "\u{0421}\u{0442}\u{0430}\u{0442}\u{0443}\u{0441}",
        "\u{0423}\u{0447}\u{0435}\u{0431}\u{043D}\u{044B}\u{0439} \u{0433}\u{043E}\u{0434}",
    ];

    public function storeUploadedFile(UploadedFile $file): array
    {
        $path = $file->store('imports/students', 'local');

        return [
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    public function process(StudentImport $studentImport): void
    {
        $studentImport->forceFill([
            'status' => 'processing',
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ])->save();

        try {
            $rows = $this->parseImportedStudentRows(
                Storage::disk($studentImport->disk)->path($studentImport->file_path),
                pathinfo($studentImport->original_name, PATHINFO_EXTENSION),
            );

            if ($rows->isEmpty()) {
                $studentImport->forceFill([
                    'status' => 'completed',
                    'total_rows' => 0,
                    'finished_at' => now(),
                    'error_rows' => [
                        ['row' => 0, 'message' => __('ui.students.import_empty')],
                    ],
                ])->save();

                return;
            }

            $stats = [
                'total_rows' => $rows->count(),
                'imported_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'error_rows' => [],
            ];

            DB::transaction(function () use ($rows, $studentImport, &$stats): void {
                foreach ($rows as $row) {
                    $result = $this->importRow($row['line'], $row['payload'], $studentImport->school_id);

                    $stats[$result['counter']]++;

                    if ($result['error'] !== null) {
                        $stats['error_rows'][] = [
                            'row' => $row['line'],
                            'message' => $result['error'],
                        ];
                    }
                }
            });

            $studentImport->forceFill([
                'status' => 'completed',
                'total_rows' => $stats['total_rows'],
                'imported_count' => $stats['imported_count'],
                'updated_count' => $stats['updated_count'],
                'skipped_count' => $stats['skipped_count'],
                'error_rows' => $stats['error_rows'],
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $studentImport->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }
    }

    public function createTemplate(string $path): void
    {
        $rows = [
            self::STUDENT_IMPORT_TEMPLATE_HEADERS,
            [
                '070101123456',
                "\u{0418}\u{0432}\u{0430}\u{043D}\u{043E}\u{0432}",
                "\u{0418}\u{0432}\u{0430}\u{043D}",
                '',
                "5\u{0410}",
                'ru',
                '1',
                MealBenefit::TYPES[0],
                '2025-2026',
            ],
        ];

        $instructionRows = [
            ["\u{0420}\u{0430}\u{0437}\u{0434}\u{0435}\u{043B}", "\u{041E}\u{043F}\u{0438}\u{0441}\u{0430}\u{043D}\u{0438}\u{0435}"],
            ["\u{0421}\u{0442}\u{0430}\u{0442}\u{0443}\u{0441}", implode(', ', MealBenefit::TYPES)],
            ["\u{042F}\u{0437}\u{044B}\u{043A}", 'ru, kk'],
            ["\u{0421}\u{043C}\u{0435}\u{043D}\u{0430}", '1, 2'],
            ["\u{0424}\u{043E}\u{0440}\u{043C}\u{0430}\u{0442} IIN", "12 \u{0446}\u{0438}\u{0444}\u{0440}, \u{043E}\u{0431}\u{044F}\u{0437}\u{0430}\u{0442}\u{0435}\u{043B}\u{044C}\u{043D}\u{043E}\u{0435} \u{043F}\u{043E}\u{043B}\u{0435}"],
            ["\u{041E}\u{0442}\u{0447}\u{0435}\u{0441}\u{0442}\u{0432}\u{043E}", "\u{041D}\u{0435}\u{043E}\u{0431}\u{044F}\u{0437}\u{0430}\u{0442}\u{0435}\u{043B}\u{044C}\u{043D}\u{043E}\u{0435} \u{043F}\u{043E}\u{043B}\u{0435}"],
            ["\u{041A}\u{043B}\u{0430}\u{0441}\u{0441}", "\u{041D}\u{0430}\u{043F}\u{0440}\u{0438}\u{043C}\u{0435}\u{0440}: 5\u{0410}, 10\u{0411}, 11\u{04D8}"],
        ];

        $this->createSimpleXlsx($path, [
            ['title' => 'Students', 'rows' => $rows],
            ['title' => 'Instructions', 'rows' => $instructionRows],
        ]);
    }

    public function parseImportedStudentRows(string $filePath, string $extension): Collection
    {
        $extension = strtolower(trim($extension));

        $rows = match ($extension) {
            'csv', 'txt' => $this->readCsvRows($filePath),
            default => $this->readXlsxRows($filePath),
        };

        if ($rows === []) {
            return collect();
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $rows[0]);
        $headerMap = $this->buildStudentImportHeaderMap($headers);

        return collect(array_slice($rows, 1))
            ->values()
            ->map(function (array $row, int $index) use ($headerMap): array {
                $payload = [];

                foreach ($headerMap as $field => $columnIndex) {
                    $payload[$field] = $row[$columnIndex] ?? null;
                }

                return [
                    'line' => $index + 2,
                    'payload' => $payload,
                ];
            })
            ->filter(function (array $row): bool {
                return collect($row['payload'])
                    ->map(fn ($value) => $this->normalizeImportedValue($value))
                    ->filter(fn ($value) => $value !== null)
                    ->isNotEmpty();
            })
            ->values();
    }

    private function importRow(int $line, array $row, ?int $schoolId): array
    {
        $iin = $this->sanitizeIin($row['iin'] ?? null);

        if ($iin === null) {
            return [
                'counter' => 'skipped_count',
                'error' => __('ui.students.import_error_iin', ['row' => $line]),
            ];
        }

        $classroomId = $this->resolveImportedClassroomId($row['classroom'] ?? null);

        if (($row['classroom'] ?? null) && $classroomId === null) {
            return [
                'counter' => 'skipped_count',
                'error' => __('ui.students.import_error_classroom', ['row' => $line]),
            ];
        }

        $benefitType = $this->normalizeImportedBenefitType($row['status'] ?? null);

        if (($row['status'] ?? null) && $benefitType === null) {
            return [
                'counter' => 'skipped_count',
                'error' => __('ui.students.import_error_status', ['row' => $line]),
            ];
        }

        $benefitStartDate = $this->normalizeImportedDate($row['benefit_start_date'] ?? null);
        $benefitEndDate = $this->normalizeImportedDate($row['benefit_end_date'] ?? null);

        if (($row['benefit_start_date'] ?? null) && $benefitStartDate === null) {
            return [
                'counter' => 'skipped_count',
                'error' => __('ui.students.import_error_date', ['row' => $line]),
            ];
        }

        if (($row['benefit_end_date'] ?? null) && $benefitEndDate === null) {
            return [
                'counter' => 'skipped_count',
                'error' => __('ui.students.import_error_date', ['row' => $line]),
            ];
        }

        $payload = [
            'iin' => $iin,
            'last_name' => $this->normalizeImportedValue($row['last_name'] ?? null),
            'first_name' => $this->normalizeImportedValue($row['first_name'] ?? null),
            'middle_name' => $this->normalizeImportedValue($row['middle_name'] ?? null),
            'classroom_id' => $classroomId,
            'school_id' => $schoolId,
            'phone' => $this->normalizeImportedValue($row['phone'] ?? null),
            'address' => $this->normalizeImportedValue($row['address'] ?? null),
            'student_number' => $this->normalizeImportedValue($row['student_number'] ?? null),
            'language' => $this->normalizeImportedLanguage($row['language'] ?? null),
            'shift' => $this->normalizeImportedShift($row['shift'] ?? null),
            'school_year' => $this->normalizeImportedValue($row['school_year'] ?? null),
            'birth_date' => $this->extractBirthDateFromIin($iin),
            'gender' => $this->extractGenderFromIin($iin),
        ];

        $student = Student::query()->where('iin', $iin)->first();
        $counter = $student ? 'updated_count' : 'imported_count';

        if ($student) {
            $student->fill($payload)->save();
        } else {
            $student = Student::query()->create($payload);
        }

        if ($benefitType !== null) {
            $student->mealBenefits()->create([
                'type' => $benefitType,
                'start_date' => $benefitStartDate,
                'end_date' => $benefitEndDate,
            ]);
        }

        return [
            'counter' => $counter,
            'error' => null,
        ];
    }

    private function readCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return [];
        }

        $rows = [];

        while (($row = fgetcsv($handle, separator: ';')) !== false) {
            if (count($row) === 1) {
                $row = str_getcsv($row[0], ',');
            }

            $rows[] = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
        }

        fclose($handle);

        return $rows;
    }

    private function readXlsxRows(string $filePath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            return [];
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if (! is_string($sheetXml) || $sheetXml === '') {
            $zip->close();

            return [];
        }

        $xml = simplexml_load_string($sheetXml);

        if ($xml === false || ! isset($xml->sheetData->row)) {
            $zip->close();

            return [];
        }

        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $currentRow = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);
                $index = $this->xlsxColumnIndex($column);
                $type = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }

                $currentRow[$index] = trim($value);
            }

            if ($currentRow === []) {
                continue;
            }

            ksort($currentRow);
            $maxIndex = max(array_keys($currentRow));
            $rows[] = array_map(
                fn ($index) => $currentRow[$index] ?? '',
                range(0, $maxIndex),
            );
        }

        $zip->close();

        return $rows;
    }

    private function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $xmlContent = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($xmlContent) || $xmlContent === '') {
            return [];
        }

        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $item) {
            if (isset($item->t)) {
                $strings[] = trim((string) $item->t);

                continue;
            }

            $value = '';

            foreach ($item->r as $run) {
                $value .= (string) $run->t;
            }

            $strings[] = trim($value);
        }

        return $strings;
    }

    private function buildStudentImportHeaderMap(array $headers): array
    {
        $map = [];

        foreach (self::STUDENT_IMPORT_HEADERS as $field => $aliases) {
            foreach ($aliases as $alias) {
                $index = array_search($this->normalizeHeader($alias), $headers, true);

                if ($index !== false) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));

        return preg_replace('/[\s\-_]+/u', '', $header) ?? $header;
    }

    private function normalizeImportedValue(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function sanitizeIin(mixed $value): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $value);

        return preg_match('/^\d{12}$/', (string) $value) ? $value : null;
    }

    private function normalizeImportedLanguage(mixed $value): ?string
    {
        $value = mb_strtolower((string) ($this->normalizeImportedValue($value) ?? ''));

        return match ($value) {
            'ru', 'рус', 'русский' => 'ru',
            'kk', 'қаз', 'каз', 'қазақша', 'казахский' => 'kk',
            default => null,
        };
    }

    private function normalizeImportedShift(mixed $value): ?int
    {
        $value = $this->normalizeImportedValue($value);

        return in_array($value, ['1', '2'], true) ? (int) $value : null;
    }

    private function normalizeImportedBenefitType(mixed $value): ?string
    {
        $value = mb_strtolower((string) ($this->normalizeImportedValue($value) ?? ''));

        if ($value === '') {
            return null;
        }

        return in_array($value, MealBenefit::TYPES, true) ? $value : null;
    }

    private function normalizeImportedDate(mixed $value): ?string
    {
        $value = $this->normalizeImportedValue($value);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveImportedClassroomId(mixed $value): ?int
    {
        $classroomName = $this->normalizeImportedValue($value);

        if ($classroomName === null) {
            return null;
        }

        $classroomName = mb_strtoupper(preg_replace('/\s+/u', '', $classroomName) ?? $classroomName);

        $classroom = AcademicClass::query()->where('full_name', $classroomName)->first();

        if ($classroom) {
            return $classroom->id;
        }

        if (! preg_match('/^(?<grade>\d+)(?<letter>.+)$/u', $classroomName, $matches)) {
            return null;
        }

        $classroom = AcademicClass::query()->create([
            'grade' => (int) $matches['grade'],
            'letter' => trim($matches['letter']),
        ]);

        return $classroom->id;
    }

    private function extractBirthDateFromIin(?string $iin): ?string
    {
        if (! preg_match('/^\d{12}$/', (string) $iin)) {
            return null;
        }

        $centuryDigit = (int) $iin[6];
        $century = match ($centuryDigit) {
            1, 2 => 1800,
            3, 4 => 1900,
            5, 6 => 2000,
            default => null,
        };

        if ($century === null) {
            return null;
        }

        $year = $century + (int) substr($iin, 0, 2);
        $month = (int) substr($iin, 2, 2);
        $day = (int) substr($iin, 4, 2);

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->format('Y-m-d');
    }

    private function extractGenderFromIin(?string $iin): ?string
    {
        if (! preg_match('/^\d{12}$/', (string) $iin)) {
            return null;
        }

        return ((int) $iin[6]) % 2 === 1 ? 'male' : 'female';
    }

    private function createSimpleXlsx(string $path, array $sheets): void
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create template archive.');
        }

        $sharedStrings = [];
        $sharedStringIndexes = [];
        $sheetContents = [];
        $workbookSheets = [];
        $workbookRelationships = [];
        $contentTypeOverrides = [];

        foreach (array_values($sheets) as $sheetIndex => $sheet) {
            $sheetId = $sheetIndex + 1;
            $sheetFile = 'sheet' . $sheetId . '.xml';
            $sheetTitle = $sheet['title'];
            $rows = $sheet['rows'];
            $sheetRows = [];

            foreach ($rows as $rowIndex => $row) {
                $cellsXml = '';

                foreach (array_values($row) as $columnIndex => $value) {
                    $value = (string) $value;
                    $sharedStringIndex = $sharedStringIndexes[$value] ?? null;

                    if ($sharedStringIndex === null) {
                        $sharedStringIndex = count($sharedStrings);
                        $sharedStringIndexes[$value] = $sharedStringIndex;
                        $sharedStrings[] = $value;
                    }

                    $cellReference = $this->xlsxColumnName($columnIndex) . ($rowIndex + 1);
                    $cellsXml .= '<c r="' . $cellReference . '" t="s"><v>' . $sharedStringIndex . '</v></c>';
                }

                $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . $cellsXml . '</row>';
            }

            $sheetContents[] = [
                'path' => 'xl/worksheets/' . $sheetFile,
                'xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                    . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
                    . '</worksheet>',
            ];

            $workbookSheets[] = '<sheet name="' . htmlspecialchars($sheetTitle, ENT_XML1) . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
            $workbookRelationships[] = '<Relationship Id="rId' . $sheetId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/' . $sheetFile . '"/>';
            $contentTypeOverrides[] = '<Override PartName="/xl/worksheets/' . $sheetFile . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        $sharedStringsRelationId = count($workbookRelationships) + 1;

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">'
            . implode('', array_map(fn (string $value): string => '<si><t>' . htmlspecialchars($value, ENT_XML1) . '</t></si>', $sharedStrings))
            . '</sst>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . implode('', $contentTypeOverrides)
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>');

        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Codex</Application>'
            . '</Properties>');

        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Students Import Template</dc:title>'
            . '</cp:coreProperties>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . implode('', $workbookSheets) . '</sheets>'
            . '</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . implode('', $workbookRelationships)
            . '<Relationship Id="rId' . $sharedStringsRelationId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>');

        foreach ($sheetContents as $sheetContent) {
            $zip->addFromString($sheetContent['path'], $sheetContent['xml']);
        }

        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);

        $zip->close();
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $name = chr(65 + $modulo) . $name;
            $index = intdiv($index - $modulo - 1, 26);
        }

        return $name;
    }

    private function xlsxColumnIndex(string $column): int
    {
        $column = strtoupper($column);
        $index = 0;

        foreach (str_split($column) as $character) {
            $index = ($index * 26) + (ord($character) - 64);
        }

        return max(0, $index - 1);
    }
}

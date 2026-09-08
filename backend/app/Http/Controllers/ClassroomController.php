<?php

namespace App\Http\Controllers;

use App\Models\AcademicClass;
use App\Models\Student;
use App\Support\QrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()?->loadMissing('roles', 'scopes');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $restrictBySchool = in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true);
        $canOpenStudents = $user !== null;
        $userSchoolId = $this->resolveSchoolIdForUser($request);
        $filters = [
            'search' => trim((string) $request->string('search')),
            'grade' => (string) $request->string('grade'),
            'filled' => (string) $request->string('filled'),
        ];
        $gradesQuery = AcademicClass::query()
            ->when($restrictBySchool && $userSchoolId !== null, function ($query) use ($userSchoolId): void {
                $query->whereHas('students', fn ($studentQuery) => $studentQuery->where('school_id', $userSchoolId));
            });

        $classesQuery = AcademicClass::query()
            ->withCount([
                'students' => fn ($query) => $query
                    ->when($restrictBySchool && $userSchoolId !== null, fn ($studentQuery) => $studentQuery->where('school_id', $userSchoolId)),
            ])
            ->when($restrictBySchool && $userSchoolId !== null, function ($query) use ($userSchoolId): void {
                $query->whereHas('students', fn ($studentQuery) => $studentQuery->where('school_id', $userSchoolId));
            })
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = mb_strtoupper($filters['search']);

                $query->whereRaw('UPPER(full_name) like ?', ['%'.$search.'%']);
            })
            ->when($filters['grade'] !== '', fn ($query) => $query->where('grade', (int) $filters['grade']))
            ->when($filters['filled'] === 'with', function ($query) use ($restrictBySchool, $userSchoolId): void {
                $query->whereHas('students', fn ($studentQuery) => $studentQuery
                    ->when($restrictBySchool && $userSchoolId !== null, fn ($schoolQuery) => $schoolQuery->where('school_id', $userSchoolId)));
            })
            ->when($filters['filled'] === 'without', function ($query) use ($restrictBySchool, $userSchoolId): void {
                $query->whereDoesntHave('students', fn ($studentQuery) => $studentQuery
                    ->when($restrictBySchool && $userSchoolId !== null, fn ($schoolQuery) => $schoolQuery->where('school_id', $userSchoolId)));
            })
            ->orderBy('grade')
            ->orderBy('letter');

        return view('classes.index', [
            'user' => $user,
            'classes' => $classesQuery->get(),
            'grades' => $gradesQuery
                ->select('grade')
                ->distinct()
                ->orderBy('grade')
                ->pluck('grade'),
            'canOpenStudents' => $canOpenStudents,
            'filters' => $filters,
            'title' => __('ui.menu.classes'),
        ]);
    }

    public function show(Request $request, AcademicClass $academicClass): View
    {
        [$user, $students] = $this->resolveAuthorizedClassroomContext($request, $academicClass);

        return view('classes.show', [
            'user' => $user,
            'classroom' => $academicClass,
            'students' => $students,
            'title' => $academicClass->full_name,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['integer', 'distinct', Rule::exists('classrooms', 'id')],
        ]);

        $user = $request->user()?->loadMissing('roles', 'scopes');
        abort_unless($user !== null, 403);

        $classroomIds = collect($data['classroom_ids'])->map(fn ($id): int => (int) $id);
        $roleCodes = $user->roles->pluck('code')->all();
        $restrictBySchool = in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true);
        $userSchoolId = $restrictBySchool ? $this->resolveSchoolIdForUser($request) : null;

        $classrooms = AcademicClass::query()
            ->whereIn('id', $classroomIds)
            ->when($userSchoolId !== null, fn ($query) => $query->whereHas(
                'students',
                fn ($studentQuery) => $studentQuery->where('school_id', $userSchoolId),
            ))
            ->with(['students' => fn ($query) => $query
                ->when($userSchoolId !== null, fn ($studentQuery) => $studentQuery->where('school_id', $userSchoolId))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('middle_name'), 'students.latestMealBenefit'])
            ->orderBy('grade')
            ->orderBy('letter')
            ->get();

        abort_if($classrooms->count() !== $classroomIds->count(), 404);

        $tempPath = tempnam(sys_get_temp_dir(), 'classes-export-');
        abort_if($tempPath === false, 500, 'Unable to create export file.');

        $xlsxPath = $tempPath.'.xlsx';
        @rename($tempPath, $xlsxPath);

        $headerStyle = (new Style)
            ->setFontBold()
            ->setBackgroundColor('DCE9F9')
            ->setCellAlignment(CellAlignment::CENTER);
        $titleStyle = (new Style)->setFontBold();
        $centerStyle = (new Style)->setCellAlignment(CellAlignment::CENTER);
        $writer = new Writer;
        $writer->openToFile($xlsxPath);
        $usedSheetNames = [];

        foreach ($classrooms->values() as $classroomIndex => $classroom) {
            $sheet = $classroomIndex === 0
                ? $writer->getCurrentSheet()
                : $writer->addNewSheetAndMakeItCurrent();
            $sheetName = $this->uniqueSheetName((string) $classroom->full_name, $usedSheetNames);
            $usedSheetNames[] = mb_strtolower($sheetName);
            $sheet->setName($sheetName);
            $sheet->setColumnWidth(6, 1);
            $sheet->setColumnWidth(34, 2);
            $sheet->setColumnWidth(16, 3);
            $sheet->setColumnWidth(16, 4);
            $sheet->setColumnWidth(14, 5);
            $sheet->setColumnWidth(18, 6);
            $sheet->setColumnWidth(12, 7);
            $sheet->setColumnWidth(16, 8);
            $sheet->setColumnWidth(16, 9);

            $writer->addRow(Row::fromValues([
                __('ui.classes.export_sheet_title', ['classroom' => $classroom->full_name]),
            ], $titleStyle));
            $writer->addRow(Row::fromValues([
                __('ui.classes.export_students_count', ['count' => $classroom->students->count()]),
            ]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([
                '№',
                __('admin.labels.full_name'),
                __('admin.labels.iin'),
                __('admin.labels.birth_date'),
                __('admin.labels.gender'),
                __('admin.labels.phone'),
                __('admin.labels.shift'),
                __('admin.labels.school_year'),
                __('admin.labels.status'),
            ], $headerStyle));

            foreach ($classroom->students as $index => $student) {
                $gender = match ($student->gender) {
                    'male' => __('admin.labels.male'),
                    'female' => __('admin.labels.female'),
                    default => '',
                };
                $benefitType = $student->latestMealBenefit?->type;
                $benefitLabel = $benefitType !== null
                    ? __('admin.meal_benefit_types.'.$benefitType)
                    : '';

                $writer->addRow(Row::fromValues([
                    $index + 1,
                    $student->full_name,
                    (string) ($student->iin ?? ''),
                    $student->birth_date?->format('d.m.Y') ?? '',
                    $gender,
                    (string) ($student->phone ?? ''),
                    $student->shift ?? '',
                    (string) ($student->school_year ?? ''),
                    $benefitLabel,
                ], $centerStyle));
            }
        }

        $writer->close();

        return response()
            ->download($xlsxPath, 'classes-'.now()->format('Y-m-d').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function downloadQrs(Request $request, AcademicClass $academicClass)
    {
        [, $students] = $this->resolveAuthorizedClassroomContext($request, $academicClass);

        abort_if($students->isEmpty(), 404);

        $zipPath = tempnam(sys_get_temp_dir(), 'class-qrs-');

        abort_if($zipPath === false, 500, 'Unable to create temporary archive file.');

        $archive = new ZipArchive;
        $archive->open($zipPath, ZipArchive::OVERWRITE);

        foreach ($students as $student) {
            $filename = $this->makeQrFilename($student);
            $png = QrCodeService::studentCardPng(
                KitchenController::studentPayload($student->id),
                $student->full_name,
                $student->classroom?->full_name ?? $academicClass->full_name
            );

            $archive->addFromString($filename, $png);
        }

        $archive->close();

        return response()->download($zipPath, 'class-'.$academicClass->full_name.'-qrs.zip')->deleteFileAfterSend(true);
    }

    private function resolveAuthorizedClassroomContext(Request $request, AcademicClass $academicClass): array
    {
        $user = $request->user()?->loadMissing('roles', 'scopes');

        abort_unless($user !== null, 403);

        $roleCodes = $user->roles->pluck('code')->all();
        $restrictBySchool = in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true);
        $userSchoolId = $restrictBySchool ? $this->resolveSchoolIdForUser($request) : null;

        if (
            $userSchoolId !== null
            && ! $academicClass->students()->where('school_id', $userSchoolId)->exists()
        ) {
            abort(404);
        }

        $students = Student::query()
            ->with('classroom')
            ->where('classroom_id', $academicClass->id)
            ->when($userSchoolId !== null, fn ($query) => $query->where('school_id', $userSchoolId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('middle_name')
            ->get();

        return [$user, $students];
    }

    private function makeQrFilename(Student $student): string
    {
        $name = $student->full_name !== '' ? $student->full_name : 'student-'.$student->id;

        return Str::slug($name, '-').'-qr.png';
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    private function uniqueSheetName(string $classroomName, array $usedNames): string
    {
        $name = trim((string) preg_replace('/[\\\\\/?*:\[\]]/u', '-', $classroomName));
        $name = $name !== '' ? $name : __('ui.classes.export_fallback_sheet');
        $candidate = mb_substr($name, 0, 31);
        $suffixNumber = 2;

        while (in_array(mb_strtolower($candidate), $usedNames, true)) {
            $suffix = ' '.$suffixNumber;
            $candidate = mb_substr($name, 0, 31 - mb_strlen($suffix)).$suffix;
            $suffixNumber++;
        }

        return $candidate;
    }

    private function resolveSchoolIdForUser(Request $request): ?int
    {
        $user = $request->user()?->loadMissing('scopes');

        if ($user?->school_id) {
            return $user->school_id;
        }

        return $user?->scopes
            ->first(fn ($scope) => $scope->scope_type === 'school' && $scope->scope_id !== null)
            ?->scope_id;
    }
}

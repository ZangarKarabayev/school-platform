<?php

namespace App\Http\Controllers;

use App\Jobs\ImportStudentsJob;
use App\Models\AcademicClass;
use App\Models\MealBenefit;
use App\Models\Student;
use App\Models\StudentImport;
use App\Modules\Organizations\Models\School;
use App\Services\Students\StudentImportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'iin' => ['nullable', 'string', 'max:12', 'unique:students,iin'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'language' => ['nullable', Rule::in(['ru', 'kk'])],
            'shift' => ['nullable', Rule::in([1, 2, '1', '2'])],
            'meal_benefit_type' => ['nullable', Rule::in(MealBenefit::TYPES)],
        ]);

        $mealBenefitType = $data['meal_benefit_type'] ?? null;
        unset($data['meal_benefit_type']);

        $data['school_id'] = $this->resolveSchoolIdForUser($request);
        $data['birth_date'] = $this->extractBirthDateFromIin($data['iin'] ?? null);
        $data['gender'] = $this->extractGenderFromIin($data['iin'] ?? null);

        $student = Student::query()->create($data);

        if ($mealBenefitType !== null && $mealBenefitType !== '') {
            $student->mealBenefits()->create([
                'type' => $mealBenefitType,
            ]);
        }

        return redirect()->route('students.edit', $student);
    }

    public function import(Request $request, StudentImportService $studentImportService): RedirectResponse
    {
        $data = $request->validate([
            'students_file' => ['required', File::types(['xlsx', 'csv', 'txt'])->max(10 * 1024)],
        ]);

        $storedFile = $studentImportService->storeUploadedFile($data['students_file']);
        $studentImport = StudentImport::query()->create([
            'user_id' => $request->user()?->id,
            'school_id' => $this->resolveSchoolIdForUser($request),
            'disk' => $storedFile['disk'],
            'file_path' => $storedFile['path'],
            'original_name' => $storedFile['original_name'],
            'status' => 'pending',
        ]);

        ImportStudentsJob::dispatch($studentImport);

        return redirect()
            ->route('students.index')
            ->with('student_status', __('ui.students.import_queued'));
    }

    public function downloadImportTemplate(StudentImportService $studentImportService): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'students-import-template-');

        if ($tempPath === false) {
            abort(500, 'Unable to create temporary template file.');
        }

        $xlsxPath = $tempPath . '.xlsx';

        @unlink($tempPath);

        $studentImportService->createTemplate($xlsxPath);

        return response()->download($xlsxPath, 'students-import-template.xlsx')->deleteFileAfterSend(true);
    }

    public function index(Request $request): View
    {
        $user = $request->user()?->loadMissing('roles', 'scopes');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $restrictClassroomFilter = in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true);
        $userSchoolId = $this->resolveSchoolIdForUser($request);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'classroom_id' => $request->integer('classroom_id') ?: null,
            'school_id' => $request->integer('school_id') ?: null,
            'status' => trim((string) $request->string('status')),
            'photo' => trim((string) $request->string('photo')),
        ];

        $students = Student::query()
            ->with(['classroom', 'school', 'latestMealBenefit'])
            ->when($restrictClassroomFilter && $userSchoolId !== null, fn ($query) => $query->where('school_id', $userSchoolId))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('iin', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['classroom_id'] !== null, fn ($query) => $query->where('classroom_id', $filters['classroom_id']))
            ->when($filters['school_id'] !== null, fn ($query) => $query->where('school_id', $filters['school_id']))
            ->when($filters['status'] !== '', function ($query) use ($filters): void {
                $query->whereHas('latestMealBenefit', function ($mealBenefitQuery) use ($filters): void {
                    $mealBenefitQuery->where('type', $filters['status']);
                });
            })
            ->when($filters['photo'] === 'with', fn ($query) => $query->whereNotNull('photo')->where('photo', '!=', ''))
            ->when($filters['photo'] === 'without', function ($query): void {
                $query->where(function ($photoQuery): void {
                    $photoQuery
                        ->whereNull('photo')
                        ->orWhere('photo', '');
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('students.index', [
            'user' => $user,
            'students' => $students,
            'classrooms' => AcademicClass::query()
                ->when($restrictClassroomFilter, function ($query) use ($userSchoolId): void {
                    $studentClassrooms = Student::query()
                        ->whereNotNull('classroom_id');

                    if ($userSchoolId !== null) {
                        $studentClassrooms->where('school_id', $userSchoolId);
                    }

                    $query->whereIn(
                        'id',
                        $studentClassrooms
                            ->distinct()
                            ->pluck('classroom_id')
                    );
                })
                ->orderBy('grade')
                ->orderBy('letter')
                ->get(),
            'schools' => School::query()->orderBy('name_ru')->orderBy('name_kk')->get(),
            'statuses' => collect(MealBenefit::TYPES),
            'studentImports' => StudentImport::query()
                ->where('user_id', $request->user()?->id)
                ->latest()
                ->limit(5)
                ->get(),
            'filters' => $filters,
            'title' => __('ui.menu.students'),
        ]);
    }

    public function edit(Request $request, Student $student): View
    {
        return view('students.edit', [
            'user' => $request->user()?->loadMissing('roles', 'scopes'),
            'student' => $student->load([
                'classroom',
                'school',
                'latestMealBenefit',
                'orders' => fn ($query) => $query
                    ->with('dish')
                    ->orderByDesc('order_date')
                    ->orderByDesc('id'),
            ]),
            'classrooms' => AcademicClass::query()->orderBy('grade')->orderBy('letter')->get(),
            'schools' => School::query()->orderBy('name_ru')->orderBy('name_kk')->get(),
            'title' => $student->full_name ?: __('admin.labels.student'),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'iin' => ['nullable', 'string', 'max:12', Rule::unique('students', 'iin')->ignore($student->id)],
            'last_name' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:65535'],
            'student_number' => ['nullable', 'string', 'max:20'],
            'language' => ['nullable', Rule::in(['ru', 'kk'])],
            'shift' => ['nullable', Rule::in([1, 2, '1', '2'])],
            'school_year' => ['nullable', 'string', 'max:9'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ]);

        $student->update($data);

        return redirect()->route('students.edit', $student);
    }

    public function destroy(Student $student): RedirectResponse
    {
        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }

        $student->delete();

        return redirect()->route('students.index');
    }

    public function updatePhoto(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'photo_file' => ['nullable', File::image()->max(5 * 1024)],
            'photo_data' => ['nullable', 'string'],
        ]);

        if (! empty($data['photo_file'])) {
            $path = $data['photo_file']->store('students/photos', 'public');
            $this->replaceStudentPhoto($student, $path);

            return back()->with('student_status', __('ui.messages.photo_updated'));
        }

        if (! empty($data['photo_data'])) {
            $path = $this->storeCameraPhoto($data['photo_data'], $student);
            $this->replaceStudentPhoto($student, $path);

            return back()->with('student_status', __('ui.messages.photo_updated'));
        }

        return back()->withErrors(['photo' => __('ui.messages.photo_required')]);
    }

    private function replaceStudentPhoto(Student $student, string $path): void
    {
        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }

        $student->forceFill([
            'photo' => $path,
            'photo_updated_at' => now(),
            'photo_synced_at' => null,
        ])->save();
    }

    private function storeCameraPhoto(string $dataUri, Student $student): string
    {
        if (! preg_match('/^data:image\/(?<type>png|jpeg|jpg);base64,(?<data>.+)$/', $dataUri, $matches)) {
            abort(422, 'Invalid camera image payload.');
        }

        $binary = base64_decode(str_replace(' ', '+', $matches['data']), true);

        if ($binary === false) {
            abort(422, 'Invalid camera image payload.');
        }

        $extension = $matches['type'] === 'jpeg' ? 'jpg' : $matches['type'];
        $path = 'students/photos/student-' . $student->id . '-' . now()->format('YmdHis') . '.' . $extension;

        Storage::disk('public')->put($path, $binary);

        return $path;
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
}

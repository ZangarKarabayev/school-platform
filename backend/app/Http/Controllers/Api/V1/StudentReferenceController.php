<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\MealBenefit;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Rules\ValidSchoolYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentReferenceController extends Controller
{
    public function classrooms(Request $request): JsonResponse
    {
        $this->schoolId($request);

        return response()->json(['classrooms' => AcademicClass::query()->where('grade', '>', 0)
            ->orderBy('grade')->orderBy('letter')->orderBy('id')
            ->get(['id', 'grade', 'letter', 'full_name'])]);
    }

    public function classes(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);
        $classes = AcademicClass::query()
            ->whereHas('students', fn ($query) => $query->where('school_id', $schoolId))
            ->withCount(['students' => fn ($query) => $query->where('school_id', $schoolId)])
            ->orderBy('grade')
            ->orderBy('letter')
            ->get(['id', 'grade', 'letter', 'full_name']);

        return response()->json(['classes' => $classes]);
    }

    public function options(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);
        $today = CarbonImmutable::today(config('app.timezone'));
        $startYear = $today->month >= 9 ? $today->year : $today->year - 1;
        $currentYear = $startYear.'-'.($startYear + 1);
        $years = Student::query()->where('school_id', $schoolId)->distinct()->pluck('school_year')
            ->merge(StudentEnrollment::query()->where('school_id', $schoolId)->distinct()->pluck('school_year'))
            ->push($currentYear)->filter(fn ($year) => ValidSchoolYear::isValid($year))
            ->unique()->sortDesc()->values();

        return response()->json([
            'default_school_year' => $currentYear,
            'school_years' => $years,
            'school_years_are_suggestions' => true,
            'languages' => collect(['ru', 'kk'])->map(fn ($value) => $this->option($value, 'admin.language.'.$value)),
            'shifts' => [
                ['value' => 1, 'label' => ['ru' => '1', 'kk' => '1']],
                ['value' => 2, 'label' => ['ru' => '2', 'kk' => '2']],
            ],
            'genders' => collect(['male', 'female'])->map(fn ($value) => $this->option($value, 'admin.labels.'.$value)),
            'meal_benefit_types' => collect(MealBenefit::TYPES)->map(fn ($value) => $this->option($value, 'admin.meal_benefit_types.'.$value)),
            'required_fields' => ['first_name', 'last_name', 'school_year'],
            'photo_max_kb' => 2048,
        ]);
    }

    private function option(string $value, string $key): array
    {
        return ['value' => $value, 'label' => ['ru' => __($key, [], 'ru'), 'kk' => __($key, [], 'kk')]];
    }

    private function schoolId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('roles.permissions', 'scopes');
        abort_unless($user->hasRole('teacher') && $user->hasPermission('students.manage'), 403, 'Teacher student management access is required.');
        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');

        return $schoolId;
    }
}

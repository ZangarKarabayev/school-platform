<?php

namespace App\Http\Controllers;

use App\Models\AcademicClass;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()?->loadMissing('roles', 'scopes');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $restrictBySchool = in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true);
        $canOpenStudents = $restrictBySchool;
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

                $query->whereRaw('UPPER(full_name) like ?', ['%' . $search . '%']);
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
        $user = $request->user()?->loadMissing('roles', 'scopes');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $canOpenStudents = in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true);

        abort_unless($canOpenStudents, 403);

        $userSchoolId = $this->resolveSchoolIdForUser($request);

        if (
            $userSchoolId !== null
            && ! $academicClass->students()->where('school_id', $userSchoolId)->exists()
        ) {
            abort(404);
        }

        $students = Student::query()
            ->where('classroom_id', $academicClass->id)
            ->when($userSchoolId !== null, fn ($query) => $query->where('school_id', $userSchoolId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('middle_name')
            ->get();

        return view('classes.show', [
            'user' => $user,
            'classroom' => $academicClass,
            'students' => $students,
            'title' => $academicClass->full_name,
        ]);
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

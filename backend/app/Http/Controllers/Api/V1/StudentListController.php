<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentListController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('scopes');
        $search = trim($request->string('search')->toString());
        $schoolId = $this->resolveSchoolIdForUser($user);

        $students = Student::query()
            ->with(['classroom', 'school', 'latestMealBenefit'])
            ->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('iin', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(100)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'iin' => $student->iin,
                'classroom' => $student->classroom?->full_name,
                'school' => $student->school?->display_name,
                'benefit' => $student->latestMealBenefit?->type,
                'photo' => filled($student->photo),
            ])
            ->values()
            ->all();

        return response()->json([
            'students' => $students,
        ]);
    }

    private function resolveSchoolIdForUser(User $user): ?int
    {
        if ($user->school_id) {
            return $user->school_id;
        }

        return $user->scopes
            ->first(fn ($scope) => $scope->scope_type === 'school' && $scope->scope_id !== null)
            ?->scope_id;
    }
}

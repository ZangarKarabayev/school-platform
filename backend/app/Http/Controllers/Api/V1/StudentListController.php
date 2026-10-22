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
        $user = $request->user()->loadMissing('scopes', 'roles.permissions');
        abort_unless($user->hasPermission('students.manage'), 403, 'Student list access is not permitted.');

        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');

        $validated = $request->validate([
            'order_date' => ['sometimes', 'date_format:Y-m-d'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'archived' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'classroom_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $orderDate = $validated['order_date'] ?? now(config('app.timezone'))->toDateString();
        $students = Student::query()
            ->when($request->boolean('archived'), fn ($query) => $query->onlyTrashed())
            ->with(['classroom', 'school', 'orders' => fn ($query) => $query->whereDate('order_date', $orderDate)->select('id', 'student_id', 'status')])
            ->where('school_id', $schoolId)
            ->when(isset($validated['classroom_id']), fn ($query) => $query->where('classroom_id', $validated['classroom_id']))
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                foreach (preg_split('/\s+/u', trim($validated['search']), flags: PREG_SPLIT_NO_EMPTY) as $term) {
                    $query->where(function ($query) use ($term): void {
                        $query->where('student_number', 'like', '%'.$term.'%')
                            ->orWhere('first_name', 'like', '%'.$term.'%')
                            ->orWhere('last_name', 'like', '%'.$term.'%')
                            ->orWhere('middle_name', 'like', '%'.$term.'%');
                    });
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 50)
            ->through(fn (Student $student): array => [
                'id' => $student->id,
                'has_order' => $student->orders->isNotEmpty(),
                'order_id' => $student->orders->first()?->id,
                'order_status' => $student->orders->first()?->status,
                'archived_at' => $student->deleted_at?->toIso8601String(),
                'full_name' => $student->full_name,
                'classroom_id' => $student->classroom_id,
                'classroom' => $student->classroom?->full_name,
                'school' => $student->school?->display_name,
                'photo' => filled($student->photo),
                'photo_url' => $student->photo_url,
            ]);

        return response()->json([
            'order_date' => $orderDate,
            'students' => $students->items(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'last_page' => $students->lastPage(),
                'total' => $students->total(),
            ],
        ]);
    }
}

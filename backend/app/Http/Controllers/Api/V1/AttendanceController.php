<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\VerifyEvent;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private function schoolId(Request $request): int
    {
        $user = $request->user()->loadMissing('roles', 'scopes');
        abort_unless($user->hasRole('teacher') || $user->hasRole('director'), 403);
        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');

        return $schoolId;
    }

    public function studentHistory(Request $request, int $student): JsonResponse
    {
        $schoolId = $this->schoolId($request);
        $person = Student::withTrashed()->with('classroom')->where('school_id', $schoolId)->findOrFail($student);
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'direction' => ['nullable', 'in:entry,exit'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = VerifyEvent::query()->where('unique_qr', (string) $person->id)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('schools')
                ->where('schools.id', $schoolId)->whereColumn('schools.bin', 'verify_events.bin'));
        foreach (['date', 'date_from'] as $field) {
            if (isset($filters[$field])) {
                $query->where('create_time', '>=', Carbon::parse($filters[$field])->startOfDay());
            }
        }
        foreach (['date', 'date_to'] as $field) {
            if (isset($filters[$field])) {
                $query->where('create_time', '<', Carbon::parse($filters[$field])->addDay()->startOfDay());
            }
        }
        if (isset($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }
        $events = $query->orderByDesc('create_time')->orderByDesc('id')->paginate($filters['per_page'] ?? 50)
            ->through(fn (VerifyEvent $event): array => [
                'id' => $event->id,
                'direction' => $event->direction,
                'create_time' => $event->create_time?->toIso8601String(),
            ]);

        return response()->json([
            'student' => [
                'id' => $person->id, 'full_name' => $person->full_name,
                'classroom_id' => $person->classroom_id, 'classroom' => $person->classroom?->full_name,
            ],
            'events' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(), 'per_page' => $events->perPage(),
                'last_page' => $events->lastPage(), 'total' => $events->total(),
            ],
        ]);
    }

    public function __invoke(Request $request, AttendanceService $service): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'classroom' => ['nullable', 'integer', 'min:1'],
            'direction' => ['nullable', 'in:entry,exit'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $data = $service->listing($filters, $schoolId);
        $serialize = static fn (VerifyEvent $event): array => [
            'id' => $event->id,
            'student' => [
                'id' => $event->student->id,
                'full_name' => $event->student->full_name,
                'classroom_id' => $event->student->classroom_id,
                'classroom' => $event->student->classroom?->full_name,
            ],
            'direction' => $event->direction,
            'create_time' => $event->create_time->toIso8601String(),
        ];
        $events = $data['events'];

        return response()->json([
            'date' => $data['date']->toDateString(),
            'stats' => $data['stats'],
            'latest' => $data['latest'] ? $serialize($data['latest']) : null,
            'classrooms' => $data['classrooms']->map(fn ($classroom) => [
                'id' => $classroom->id, 'name' => $classroom->full_name,
            ])->values(),
            'events' => $events->getCollection()->map($serialize)->values(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }
}

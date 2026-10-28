<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VerifyEvent;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __invoke(Request $request, AttendanceService $service): JsonResponse
    {
        $user = $request->user()->loadMissing('roles', 'scopes');
        abort_unless($user->hasRole('teacher') || $user->hasRole('director'), 403);
        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');

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

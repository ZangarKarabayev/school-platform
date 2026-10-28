<?php

namespace App\Services\Attendance;

use App\Models\AcademicClass;
use App\Models\Student;
use App\Models\VerifyEvent;
use Carbon\Carbon;

class AttendanceService
{
    public function listing(array $filters, ?int $schoolId): array
    {
        $date = Carbon::parse($filters['date'] ?? now()->toDateString());
        $students = Student::query()
            ->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId));
        $events = VerifyEvent::query()
            ->whereIn('unique_qr', (clone $students)->selectRaw('CAST(students.id AS TEXT)'))
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('students as attendee')
                    ->join('schools', 'schools.id', '=', 'attendee.school_id')
                    ->whereRaw('CAST(attendee.id AS TEXT) = verify_events.unique_qr')
                    ->whereColumn('schools.bin', 'verify_events.bin');
            })
            ->where('create_time', '>=', $date->copy()->startOfDay())
            ->where('create_time', '<', $date->copy()->addDay()->startOfDay());
        // Use event time, then ID, to handle delayed terminal messages.
        $latestEvents = (clone $events)->whereNotExists(function ($query) use ($date) {
            $query->selectRaw('1')->from('verify_events as newer')
                ->whereColumn('newer.unique_qr', 'verify_events.unique_qr')
                ->whereColumn('newer.bin', 'verify_events.bin')
                ->where('newer.create_time', '<', $date->copy()->addDay()->startOfDay())
                ->where(function ($query) {
                    $query->whereColumn('newer.create_time', '>', 'verify_events.create_time')
                        ->orWhere(function ($query) {
                            $query->whereColumn('newer.create_time', 'verify_events.create_time')->whereColumn('newer.id', '>', 'verify_events.id');
                        });
                });
        });
        $states = $latestEvents->get();
        $stats = [
            'total' => (clone $students)->count(),
            'inside' => $states->filter(fn (VerifyEvent $event) => $event->direction === 'entry' || $event->direction === null)->count(),
            'outside' => $states->where('direction', 'exit')->count(),
        ];
        $stats['absent'] = $stats['total'] - $states->count();
        $latest = (clone $events)->with('student.classroom')->orderByDesc('create_time')->orderByDesc('id')->first();
        $classrooms = AcademicClass::query()->whereIn('id', (clone $students)->select('classroom_id'))->orderBy('grade')->orderBy('letter')->get();
        if (! empty($filters['classroom'])) {
            $students->where('classroom_id', $filters['classroom']);
        }
        if (! empty($filters['search'])) {
            foreach (preg_split('/\s+/u', trim($filters['search'])) as $word) {
                $students->where(fn ($q) => $q->where('first_name', 'like', "%{$word}%")->orWhere('last_name', 'like', "%{$word}%")->orWhere('middle_name', 'like', "%{$word}%"));
            }
        }
        $events->whereIn('unique_qr', $students->selectRaw('CAST(students.id AS TEXT)'));
        if (! empty($filters['direction'])) {
            $events->where('direction', $filters['direction']);
        }
        // Keep the first scan per student for this day, before pagination.
        $events->whereNotExists(function ($query) use ($date, $filters) {
            $query->selectRaw('1')->from('verify_events as earlier')
                ->whereColumn('earlier.unique_qr', 'verify_events.unique_qr')
                ->whereColumn('earlier.bin', 'verify_events.bin')
                ->where('earlier.create_time', '>=', $date->copy()->startOfDay())
                ->when(! empty($filters['direction']), fn ($query) => $query->where('earlier.direction', $filters['direction']))
                ->where(function ($query) {
                    $query->whereColumn('earlier.create_time', '<', 'verify_events.create_time')
                        ->orWhere(function ($query) {
                            $query->whereColumn('earlier.create_time', 'verify_events.create_time')
                                ->whereColumn('earlier.id', '<', 'verify_events.id');
                        });
                });
        });
        $events = $events->with('student.classroom')->orderByDesc('create_time')->orderByDesc('id')->paginate($filters['per_page'] ?? 15)->withQueryString();

        return compact('date', 'stats', 'latest', 'classrooms', 'events');
    }
}

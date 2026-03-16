<?php

namespace App\Http\Controllers;

use App\Jobs\CreateOrdersJob;
use App\Models\AcademicClass;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()?->loadMissing('roles', 'scopes');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $restrictBySchool = in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true);
        $userSchoolId = $user?->school_id;
        $filters = [
            'search' => trim((string) $request->string('search')),
            'order_date' => (string) $request->string('order_date'),
            'status' => (string) $request->string('status'),
            'transaction_status' => (string) $request->string('transaction_status'),
            'transaction_error' => trim((string) $request->string('transaction_error')),
        ];

        $orders = Order::query()
            ->with(['student', 'dish'])
            ->when($restrictBySchool && $userSchoolId !== null, function ($query) use ($userSchoolId): void {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('school_id', $userSchoolId));
            })
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->whereHas('student', function ($studentQuery) use ($search): void {
                    $studentQuery->where(function ($studentSearchQuery) use ($search): void {
                        $studentSearchQuery
                            ->where('iin', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('first_name', 'like', '%' . $search . '%')
                            ->orWhere('middle_name', 'like', '%' . $search . '%');
                    });
                });
            })
            ->when($filters['order_date'] !== '', fn ($query) => $query->whereDate('order_date', $filters['order_date']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['transaction_status'] !== '', function ($query) use ($filters): void {
                $query->where('transaction_status', $filters['transaction_status'] === '1');
            })
            ->when(
                $filters['transaction_error'] !== '',
                fn ($query) => $query->where('transaction_error', 'like', '%' . $filters['transaction_error'] . '%')
            )
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $students = Student::query()
            ->with('classroom')
            ->when($restrictBySchool && $userSchoolId !== null, fn ($query) => $query->where('school_id', $userSchoolId))
            ->get()
            ->sortBy(function (Student $student): string {
                $grade = str_pad((string) ($student->classroom?->grade ?? 999), 3, '0', STR_PAD_LEFT);
                $letter = mb_strtoupper((string) ($student->classroom?->letter ?? 'ZZZ'));
                $lastName = mb_strtoupper((string) $student->last_name);
                $firstName = mb_strtoupper((string) $student->first_name);
                $middleName = mb_strtoupper((string) $student->middle_name);

                return implode('|', [$grade, $letter, $lastName, $firstName, $middleName]);
            })
            ->values();

        return view('orders.index', [
            'user' => $user,
            'orders' => $orders,
            'students' => $students,
            'classrooms' => AcademicClass::query()
                ->whereIn('id', $students->pluck('classroom_id')->filter()->unique()->values())
                ->orderBy('grade')
                ->orderBy('letter')
                ->get(),
            'dishes' => Dish::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
            'statuses' => Order::query()
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status'),
            'title' => __('ui.menu.orders'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['all', 'classes', 'students'])],
            'classroom_ids' => ['nullable', 'array'],
            'classroom_ids.*' => ['integer', 'exists:classrooms,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'order_date' => ['required', 'date'],
            'order_time' => ['nullable', 'date_format:H:i'],
        ]);

        $studentIds = $this->resolveTargetStudentIds($request, $data);

        if ($studentIds->isEmpty()) {
            return redirect()->route('orders.index')
                ->withErrors(['target_type' => __('ui.orders.no_target_students')]);
        }

        CreateOrdersJob::dispatch(
            $studentIds->map(fn ($studentId) => (int) $studentId)->all(),
            $data['order_date'],
            $data['order_time'] ?? null,
        );

        return redirect()
            ->route('orders.index')
            ->with('order_status', __('ui.orders.create_queued'));
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user()?->loadMissing('roles');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $restrictBySchool = in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true);
        $userSchoolId = $user?->school_id;

        if (
            $restrictBySchool
            && $userSchoolId !== null
            && (int) $order->student?->school_id !== (int) $userSchoolId
        ) {
            abort(403);
        }

        $order->delete();

        return redirect()->route('orders.index');
    }

    private function resolveTargetStudentIds(Request $request, array $data): Collection
    {
        $user = $request->user()?->loadMissing('roles');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $restrictBySchool = in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true);
        $userSchoolId = $user?->school_id;

        $query = Student::query()
            ->when($restrictBySchool && $userSchoolId !== null, fn ($studentQuery) => $studentQuery->where('school_id', $userSchoolId));

        return match ($data['target_type']) {
            'all' => $query->pluck('id'),
            'classes' => $query
                ->whereIn('classroom_id', $data['classroom_ids'] ?? [])
                ->pluck('id'),
            'students' => $query
                ->whereIn('id', $data['student_ids'] ?? [])
                ->pluck('id'),
            default => collect(),
        };
    }
}
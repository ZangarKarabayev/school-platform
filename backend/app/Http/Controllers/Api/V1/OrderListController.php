<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Rules\ValidSchoolYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function studentOrders(Request $request, int $student): JsonResponse
    {
        return $this->listing($request, $student);
    }

    private function listing(Request $request, ?int $studentId = null): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('roles.permissions', 'scopes');
        abort_unless($user->hasRole('teacher') && $user->hasPermission('students.manage'), 403, 'Teacher order access is required.');
        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');

        if ($studentId !== null) {
            Student::withTrashed()->where('school_id', $schoolId)->findOrFail($studentId);
        }

        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'student_id' => ['sometimes', 'integer', 'min:1'],
            'classroom_id' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'order_date' => ['sometimes', 'date_format:Y-m-d'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', ...($request->has('date_from') ? ['after_or_equal:date_from'] : [])],
            'school_year' => ['sometimes', 'string', new ValidSchoolYear],
            'status' => ['sometimes', Rule::in([Order::STATUS_CREATED, Order::STATUS_ISSUED, Order::STATUS_COMPLETED, Order::STATUS_FAILED])],
            'transaction_status' => ['sometimes', 'boolean'],
        ]);

        $query = Order::query()
            ->with(['student', 'classroom', 'dish'])
            ->whereHas('student', fn ($query) => $query->where('school_id', $schoolId));

        if ($studentId !== null) {
            $query->where('student_id', $studentId);
        }
        foreach (['student_id', 'classroom_id', 'school_year', 'status', 'transaction_status'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['order_date'])) {
            $query->whereDate('order_date', $filters['order_date']);
        }
        if (isset($filters['date_from'])) {
            $query->whereDate('order_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('order_date', '<=', $filters['date_to']);
        }
        foreach (preg_split('/\s+/u', trim($filters['search'] ?? ''), flags: PREG_SPLIT_NO_EMPTY) as $term) {
            $query->whereHas('student', fn ($query) => $query->where(fn ($query) => $query
                ->where('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%')
                ->orWhere('middle_name', 'like', '%'.$term.'%')));
        }

        $orders = $query->orderByDesc('order_date')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 50)
            ->through(fn (Order $order): array => [
                'id' => $order->id,
                'student' => [
                    'id' => $order->student->id,
                    'full_name' => $order->student->full_name,
                ],
                'classroom_id' => $order->classroom_id,
                'classroom' => $order->classroom?->full_name,
                'school_year' => $order->school_year,
                'dish' => $order->dish ? ['id' => $order->dish->id, 'name' => $order->dish->name] : null,
                'order_date' => $order->order_date->toDateString(),
                'order_time' => $order->order_time,
                'status' => $order->status,
                'completed_at' => $order->completed_at?->toIso8601String(),
                'transaction_status' => $order->transaction_status,
            ]);

        return response()->json([
            'orders' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}

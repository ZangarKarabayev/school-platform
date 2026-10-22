<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\ValidSchoolYear;
use App\Services\Orders\OrderCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderCreateController extends Controller
{
    public function __invoke(Request $request, OrderCreationService $orders): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('roles.permissions', 'scopes');
        abort_unless($user->hasRole('teacher') && $user->hasPermission('students.manage'), 403, 'Teacher order access is required.');
        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');
        $data = $request->validate([
            'target_type' => ['prohibited'],
            'classroom_ids' => ['prohibited'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'order_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'school_year' => ['sometimes', 'string', new ValidSchoolYear],
        ]);
        $data['school_year'] ??= $orders->schoolYearForDate($data['order_date']);
        $orders->validateRequestDate($data);

        return response()->json($orders->create($data, $schoolId, $user->id));
    }
}

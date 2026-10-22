<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\DashboardDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    public function __invoke(Request $request, DashboardDataService $dashboardData): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('roles.permissions');
        abort_unless($user->hasRole('teacher') && $user->hasPermission('students.manage'), 403, 'Teacher dashboard access is required.');

        $data = $dashboardData->build($user, [
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'scope_kind' => 'school',
            'school_id' => $user->assignedSchoolId(),
        ], schoolOnly: true);

        unset($data['scopeConfig']);

        return response()->json($data);
    }
}

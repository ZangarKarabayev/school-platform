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
        $user = $request->user();

        $data = $dashboardData->build($user, [
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'scope_kind' => $request->string('scope_kind')->toString(),
            'school_id' => $request->integer('school_id') ?: null,
            'district_id' => $request->integer('district_id') ?: null,
            'region_id' => $request->integer('region_id') ?: null,
        ]);

        unset($data['scopeConfig']);

        return response()->json($data);
    }
}

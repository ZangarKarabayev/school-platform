<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderDeleteController extends Controller
{
    public function __invoke(Request $request, int $order): Response
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('roles.permissions', 'scopes');
        abort_unless($user->hasRole('teacher') && $user->hasPermission('students.manage'), 403, 'Teacher order access is required.');
        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');

        DB::transaction(function () use ($order, $schoolId): void {
            $record = Order::withTrashed()->whereHas('student', fn ($query) => $query->where('school_id', $schoolId))
                ->lockForUpdate()->findOrFail($order);
            if (! $record->trashed()) {
                $record->delete();
            }
        });

        return response()->noContent();
    }
}

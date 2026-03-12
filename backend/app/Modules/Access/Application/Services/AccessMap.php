<?php

namespace App\Modules\Access\Application\Services;

use App\Models\User;

class AccessMap
{
    public function permissionsFor(User $user): array
    {
        return $user->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }
}

<?php

namespace App\Http\Middleware;

use App\Modules\Access\Enums\RoleCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKitchenAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('kitchen') ?? $request->user();

        abort_if($user === null, 401);

        $user->loadMissing('roles');

        $allowedRoles = [
            RoleCode::Kitchen->value,
            RoleCode::SuperAdmin->value,
            RoleCode::SupportAdmin->value,
        ];

        abort_unless(
            collect($allowedRoles)->contains(fn (string $roleCode): bool => $user->hasRole($roleCode)),
            403,
            'Kitchen access is allowed only for kitchen staff.'
        );

        return $next($request);
    }
}
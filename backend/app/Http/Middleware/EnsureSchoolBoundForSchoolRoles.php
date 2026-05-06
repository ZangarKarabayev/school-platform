<?php

namespace App\Http\Middleware;

use App\Modules\Access\Enums\RoleCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolBoundForSchoolRoles
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->loadMissing('roles');

        if ($user === null) {
            return $next($request);
        }

        $requiresSchool = $user->hasRole(RoleCode::Teacher->value)
            || $user->hasRole(RoleCode::Director->value)
            || $user->hasRole(RoleCode::Kitchen->value)
            || $user->hasRole(RoleCode::Library->value);

        if ($requiresSchool && $user->school_id === null) {
            return redirect()->route('auth.pending', ['reason' => 'school']);
        }

        return $next($request);
    }
}

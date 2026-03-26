<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureKitchenAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('kitchen')->check()) {
            return redirect()->guest(route('kitchen.login'));
        }

        return $next($request);
    }
}
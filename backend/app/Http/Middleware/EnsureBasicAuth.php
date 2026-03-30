<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUsername = (string) config('services.voucher_api.username');
        $expectedPassword = (string) config('services.voucher_api.password');

        $username = (string) $request->getUser();
        $password = (string) $request->getPassword();

        if (
            $expectedUsername === ''
            || $expectedPassword === ''
            || !hash_equals($expectedUsername, $username)
            || !hash_equals($expectedPassword, $password)
        ) {
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()
            ->json([
                'result' => 'error',
                'code' => '401',
                'error' => 'Unauthorized',
            ], 401)
            ->header('WWW-Authenticate', 'Basic realm="Voucher API"');
    }
}

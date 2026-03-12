<?php

use App\Modules\Identity\Http\Controllers\Api\V1\EdsAuthController;
use App\Modules\Identity\Http\Controllers\Api\V1\PhoneAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', static fn (): array => [
        'status' => 'ok',
        'service' => config('app.name'),
        'timestamp' => now()->toIso8601String(),
    ]);

    Route::prefix('auth')->group(function (): void {
        Route::post('/phone/request-otp', [PhoneAuthController::class, 'requestOtp']);
        Route::post('/phone/verify-otp', [PhoneAuthController::class, 'verifyOtp']);
        Route::post('/eds/challenge', [EdsAuthController::class, 'start']);
        Route::post('/eds/verify', [EdsAuthController::class, 'verify']);
        Route::middleware('auth:api')->get('/me', [PhoneAuthController::class, 'me']);
    });
});

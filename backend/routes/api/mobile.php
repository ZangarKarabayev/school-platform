<?php

use App\Http\Controllers\Api\V1\DashboardSummaryController;
use App\Http\Controllers\Api\V1\StudentListController;
use App\Modules\Identity\Http\Controllers\Api\V1\EdsAuthController;
use App\Modules\Identity\Http\Controllers\Api\V1\PhoneAuthController;
use Illuminate\Support\Facades\Route;

// Loaded by routes/api.php within Laravel's API middleware group and /api prefix.
Route::prefix('mobile/v1/auth')->group(function (): void {
    Route::middleware('throttle:mobile-login')->group(function (): void {
        Route::post('/phone/login', [PhoneAuthController::class, 'login']);
        Route::post('/phone/request-otp', [PhoneAuthController::class, 'requestOtp']);
        Route::post('/phone/verify-otp', [PhoneAuthController::class, 'verifyOtp']);
    });

    Route::post('/eds/challenge', [EdsAuthController::class, 'start']);
    Route::post('/eds/verify', [EdsAuthController::class, 'verify']);

    Route::middleware('auth:api')->group(function (): void {
        Route::get('/me', [PhoneAuthController::class, 'me']);
        Route::post('/logout', [PhoneAuthController::class, 'logout']);
        Route::post('/logout-all', [PhoneAuthController::class, 'logoutAll']);
    });
});

Route::prefix('mobile/v1')->middleware('auth:api')->group(function (): void {
    Route::get('/dashboard', DashboardSummaryController::class);
    Route::get('/students', StudentListController::class);
});

<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\DashboardSummaryController;
use App\Http\Controllers\Api\V1\LibraryController;
use App\Http\Controllers\Api\V1\OrderCreateController;
use App\Http\Controllers\Api\V1\OrderDeleteController;
use App\Http\Controllers\Api\V1\OrderListController;
use App\Http\Controllers\Api\V1\StudentListController;
use App\Http\Controllers\Api\V1\StudentManageController;
use App\Http\Controllers\Api\V1\StudentReferenceController;
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
    Route::get('/attendance', AttendanceController::class);
    Route::get('/students/{student}/attendance', [AttendanceController::class, 'studentHistory'])->whereNumber('student');
    Route::get('/students/{student}/books', [LibraryController::class, 'studentBooks'])->whereNumber('student');
    Route::prefix('library')->controller(LibraryController::class)->group(function (): void {
        Route::get('/books', 'books');
        Route::get('/books/{book}', 'showBook')->whereNumber('book');
        Route::post('/books', 'storeBook');
        Route::put('/books/{book}', 'updateBook')->whereNumber('book');
        Route::get('/stocks', 'stocks');
        Route::post('/stocks', 'storeStock');
        Route::get('/loans', 'loans');
        Route::get('/reports', 'reports');
        Route::get('/lookup', 'lookup');
        Route::post('/operations', 'operate');
    });
    Route::get('/dashboard', DashboardSummaryController::class);
    Route::post('/orders', OrderCreateController::class);
    Route::delete('/orders/{order}', OrderDeleteController::class)->whereNumber('order');
    Route::get('/orders', [OrderListController::class, 'index']);
    Route::get('/students/{student}/orders', [OrderListController::class, 'studentOrders'])->whereNumber('student');
    Route::get('/classrooms', [StudentReferenceController::class, 'classrooms']);
    Route::get('/classes', [StudentReferenceController::class, 'classes']);
    Route::get('/students/options', [StudentReferenceController::class, 'options']);
    Route::get('/students', StudentListController::class);
    Route::post('/students/{student}/restore', [StudentManageController::class, 'restore'])->whereNumber('student');
    Route::post('/students', [StudentManageController::class, 'store']);
    Route::post('/students/{student}/photo', [StudentManageController::class, 'updatePhoto'])->whereNumber('student');
    Route::delete('/students/{student}/photo', [StudentManageController::class, 'deletePhoto'])->whereNumber('student');
    Route::get('/students/{student}', [StudentManageController::class, 'show'])->whereNumber('student');
    Route::patch('/students/{student}', [StudentManageController::class, 'update'])->whereNumber('student');
    Route::put('/students/{student}', [StudentManageController::class, 'update'])->whereNumber('student');
    Route::delete('/students/{student}', [StudentManageController::class, 'destroy'])->whereNumber('student');
});

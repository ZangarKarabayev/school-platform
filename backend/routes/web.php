<?php

use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::get('/account-pending', [WebAuthController::class, 'showPendingActivation'])->name('auth.pending');

Route::middleware('guest')->group(function (): void {
    Route::get('/', fn() => redirect()->route('login'));
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::get('/login/eds', [WebAuthController::class, 'showEdsLogin'])->name('login.eds');
    Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
    Route::get('/register/phone', [WebAuthController::class, 'showPhoneRegister'])->name('register.phone');
    Route::get('/register/eds', [WebAuthController::class, 'showEdsRegister'])->name('register.eds');

    Route::post('/login/phone', [WebAuthController::class, 'loginByPhone'])->name('login.phone');
    Route::post('/login/eds/challenge', [WebAuthController::class, 'createLoginEdsChallenge'])->name('login.eds.challenge');
    Route::post('/eds/preview', [WebAuthController::class, 'previewEdsIdentity'])->name('eds.preview');
    Route::post('/login/eds', [WebAuthController::class, 'loginByEds'])->name('login.eds.verify');

    Route::post('/register/phone', [WebAuthController::class, 'registerByPhone'])->name('register.phone.store');
    Route::post('/register/eds/challenge', [WebAuthController::class, 'createRegisterEdsChallenge'])->name('register.eds.challenge');
    Route::post('/register/eds', [WebAuthController::class, 'registerByEds'])->name('register.eds.complete');
});

Route::get('/kitchen', [KitchenController::class, 'index'])->name('kitchen.index');
Route::post('/kitchen/scan', [KitchenController::class, 'scan'])->name('kitchen.scan');
Route::get('/kitchen/{token}', [KitchenController::class, 'access'])->name('kitchen.access');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [WebAuthController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profile', [WebAuthController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [WebAuthController::class, 'updatePassword'])->name('profile.password.update');
    Route::middleware('school.bound')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/dashboard/export-orders-table', [DashboardController::class, 'exportOrdersTable'])->name('dashboard.export-orders-table');
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
        Route::post('/students/import', [StudentController::class, 'import'])->name('students.import');
        Route::post('/students/bulk-meal-benefit', [StudentController::class, 'bulkUpdateMealBenefit'])->name('students.bulk-meal-benefit');
        Route::get('/students/import-template', [StudentController::class, 'downloadImportTemplate'])->name('students.import.template');
        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::post('/students/{student}/photo', [StudentController::class, 'updatePhoto'])->name('students.photo.update');
        Route::get('/students/{student}/qr.png', [KitchenController::class, 'studentQr'])->name('students.qr');
        Route::get('/classes', [ClassroomController::class, 'index'])->name('classes.index');
        Route::get('/classes/{academicClass}/qrs.zip', [ClassroomController::class, 'downloadQrs'])->name('classes.qr.download');
        Route::get('/classes/{academicClass}', [ClassroomController::class, 'show'])->name('classes.show');
        Route::get('/dishes', [DishController::class, 'index'])->name('dishes.index');
        Route::post('/dishes', [DishController::class, 'store'])->name('dishes.store');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::view('/library', 'sections.show', [
            'sectionKey' => 'library',
        ])->name('library.index');
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::post('/reports', [ReportsController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}/download', [ReportsController::class, 'download'])->name('reports.download');
        Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::view('/support', 'sections.show', [
            'sectionKey' => 'support',
        ])->name('support.index');
    });
    Route::get('/students/{student}/photo', [StudentController::class, 'showPhoto'])->name('students.photo.show');
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
});

Route::get('/php-com-check', function () {
    return response()->json([
        'php_ini' => php_ini_loaded_file(),
        'com_dotnet_loaded' => extension_loaded('com_dotnet'),
        'com_class_exists' => class_exists('COM'),
    ]);
});

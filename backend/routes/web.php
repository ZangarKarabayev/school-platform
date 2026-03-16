<?php

use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

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

    Route::post('/register/phone', [WebAuthController::class, 'registerByPhone'])->name('register.phone');
    Route::post('/register/eds/challenge', [WebAuthController::class, 'createRegisterEdsChallenge'])->name('register.eds.challenge');
    Route::post('/register/eds', [WebAuthController::class, 'registerByEds'])->name('register.eds.complete');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [WebAuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::post('/students/import', [StudentController::class, 'import'])->name('students.import');
    Route::get('/students/import-template', [StudentController::class, 'downloadImportTemplate'])->name('students.import.template');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::post('/students/{student}/photo', [StudentController::class, 'updatePhoto'])->name('students.photo.update');
    Route::view('/classes', 'sections.show', [
        'sectionKey' => 'classes',
    ])->name('classes.index');
    Route::view('/kitchen', 'sections.show', [
        'sectionKey' => 'kitchen',
    ])->name('kitchen.index');
    Route::get('/dishes', [DishController::class, 'index'])->name('dishes.index');
    Route::post('/dishes', [DishController::class, 'store'])->name('dishes.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::view('/library', 'sections.show', [
        'sectionKey' => 'library',
    ])->name('library.index');
    Route::view('/reports', 'sections.show', [
        'sectionKey' => 'reports',
    ])->name('reports.index');
    Route::view('/devices', 'sections.show', [
        'sectionKey' => 'devices',
    ])->name('devices.index');
    Route::view('/support', 'sections.show', [
        'sectionKey' => 'support',
    ])->name('support.index');
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
});


Route::get('/php-com-check', function () {
    return response()->json([
        'php_ini' => php_ini_loaded_file(),
        'com_dotnet_loaded' => extension_loaded('com_dotnet'),
        'com_class_exists' => class_exists('COM'),
    ]);
});

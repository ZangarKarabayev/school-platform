<?php

use App\Http\Controllers\Auth\WebAuthController;
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
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
});


Route::get('/php-com-check', function () {
    return response()->json([
        'php_ini' => php_ini_loaded_file(),
        'com_dotnet_loaded' => extension_loaded('com_dotnet'),
        'com_class_exists' => class_exists('COM'),
    ]);
});

<?php

use App\Http\Controllers\Api\V1\FaceIdController;
use App\Http\Controllers\Api\V1\VoucherController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/api/mobile.php';

Route::get('/openapi.json', static fn () => response()->file(
    base_path('docs/openapi-mobile.json'),
    ['Content-Type' => 'application/json'],
))->name('api.openapi');

Route::prefix('v1')->group(function (): void {
    Route::get('/health', static fn (): array => [
        'status' => 'ok',
        'service' => config('app.name'),
        'timestamp' => now()->toIso8601String(),
    ]);

    Route::post('/faceid/events', [FaceIdController::class, 'storeEventFaceID']);

});

Route::post('/Subscribe/Verify', [FaceIdController::class, 'storeVerify'])->name('order.faceIdVerify');
Route::post('/Subscribe/Heartbeat', [FaceIdController::class, 'storeHeartbeat'])->name('order.faceIdHeartbeat');

Route::middleware(['basic.auth'])->group(function (): void {
    Route::get('/activate-voucher', [VoucherController::class, 'activateVoucher']);
    Route::get('/voucher-history', [VoucherController::class, 'getVoucherHistory']);
});

<?php

use App\Http\Controllers\Api\Pos\PosTerminalApiController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/pos')->group(function () {
    Route::get('/catalog-delta', [PosTerminalApiController::class, 'catalogDelta']);
    Route::post('/sync', [PosTerminalApiController::class, 'syncSales']);
});

Route::middleware(['throttle:5,60'])->group(function () {
    Route::post('/register', [RegistrationController::class, 'register']);
});

Route::get('/verify-email', [RegistrationController::class, 'verifyEmail']);
Route::post('/verify-phone', [RegistrationController::class, 'verifyPhone']);

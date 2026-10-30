<?php

use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:5,60'])->group(function () {
    Route::post('/register', [RegistrationController::class, 'register']);
});

Route::get('/verify-email', [RegistrationController::class, 'verifyEmail']);
Route::post('/verify-phone', [RegistrationController::class, 'verifyPhone']);

<?php

use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/signup', fn () => redirect('/register'));
Route::get('/register', [RegistrationController::class, 'showForm'])->name('register');
Route::post('/register', [RegistrationController::class, 'register'])->middleware('throttle:5,60');
Route::get('/verify-email', [RegistrationController::class, 'verifyEmail']);
Route::post('/verify-phone', [RegistrationController::class, 'verifyPhone']);

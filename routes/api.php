<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\RegisterController;

Route::controller(RegisterController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
});

Route::middleware('auth:sanctum')->controller(RegisterController::class)->group(function () {
    Route::get('roles', 'roles');
    Route::get('users', 'users');
    Route::post('register', 'register');
});
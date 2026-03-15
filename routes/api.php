<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;

Route::controller(RegisterController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::controller(RegisterController::class)->group(function () {
        Route::get('me', 'me');
        Route::get('roles', 'roles');
        Route::get('users', 'users');
        Route::post('register', 'register');
    });

    Route::controller(ProfileController::class)->group(function () {
        Route::get('profile', 'show');
        Route::post('profile', 'update');
    });

    Route::apiResource('properties', PropertyController::class);
});
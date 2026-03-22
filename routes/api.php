<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\ContractController;

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
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('contracts', ContractController::class);

   

    Route::controller(TaskController::class)->group(function () {
        Route::get('tasks', 'index');
        Route::post('tasks', 'store');
        Route::get('tasks/{task}', 'show');
        Route::put('tasks/{task}', 'update');
        Route::delete('tasks/{task}', 'destroy');

        Route::post('tasks/{task}/assign-workers', 'assignWorkers');
        Route::get('my-tasks', 'myTasks');
    });
});
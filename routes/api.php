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
    /*
    |--------------------------------------------------------------------------
    | Logged-in user's own profile
    |--------------------------------------------------------------------------
    */
    Route::controller(ProfileController::class)->group(function () {
        Route::get('me', 'show');
        Route::post('me', 'update');
        Route::patch('me', 'update');

        Route::get('profile', 'show');
        Route::post('profile', 'update');
        Route::patch('profile', 'update');
    });

    /*
    |--------------------------------------------------------------------------
    | User management
    |--------------------------------------------------------------------------
    */
    Route::controller(RegisterController::class)->group(function () {
        Route::get('roles', 'roles');

        Route::get('users', 'users');
        Route::post('register', 'register');

        Route::get('users/{user}', 'showUser');
        Route::put('users/{user}', 'updateUser');
        Route::patch('users/{user}', 'updateUser');
        Route::delete('users/{user}', 'destroyUser');

        /*
         | These aliases are for your admin profile page.
         | Example: /api/users/3/profile
         */
        Route::get('users/{user}/profile', 'showUser');
        Route::post('users/{user}/profile', 'updateUser');
        Route::patch('users/{user}/profile', 'updateUser');
    });

    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('contracts', ContractController::class);

    Route::controller(TaskController::class)->group(function () {
        Route::get('tasks', 'index');
        Route::post('tasks', 'store');

        Route::get('tasks/weekly-report', 'weeklyReport');
        Route::get('my-tasks', 'myTasks');

        Route::post('tasks/{task}/assign-workers', 'assignWorkers');
        Route::post('tasks/{task}/sync-workers', 'syncWorkers');

        Route::get('tasks/{task}/rewards', 'rewards');
        Route::post('tasks/{task}/reward', 'saveReward');
        Route::post('tasks/{task}/rewards', 'saveReward');

        Route::get('tasks/{task}', 'show');
        Route::put('tasks/{task}', 'update');
        Route::patch('tasks/{task}', 'update');
        Route::delete('tasks/{task}', 'destroy');
    });
});
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\ContractController;
use App\Http\Controllers\API\ContactMessageController;
use App\Http\Controllers\API\SupportAiController;
use App\Http\Controllers\API\MonthlyPlanPageController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::controller(RegisterController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
});

/*
|--------------------------------------------------------------------------
| Public Contact Us route
|--------------------------------------------------------------------------
| This route is public because website visitors must be able to send messages.
*/
Route::post('contact-messages', [ContactMessageController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Public Support AI Chatbot route
|--------------------------------------------------------------------------
| Website visitors can ask the chatbot questions without logging in.
*/
Route::post('support-ai/chat', [SupportAiController::class, 'chat']);

/*
|--------------------------------------------------------------------------
| Public Monthly Plan Page route
|--------------------------------------------------------------------------
| Website visitors can view the monthly plans page without logging in.
*/
Route::get('monthly-plan-page', [MonthlyPlanPageController::class, 'show']);


/*
|--------------------------------------------------------------------------
| Protected routes
|--------------------------------------------------------------------------
*/

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

    /*
    |--------------------------------------------------------------------------
    | Main resources
    |--------------------------------------------------------------------------
    */
    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('contracts', ContractController::class);

    /*
    |--------------------------------------------------------------------------
    | Monthly Plan Page Management
    |--------------------------------------------------------------------------
    | Admin can create or update the monthly plans page content from dashboard.
    */
    Route::post('monthly-plan-page', [MonthlyPlanPageController::class, 'storeOrUpdate']);

    /*
    |--------------------------------------------------------------------------
    | Contact messages management
    |--------------------------------------------------------------------------
    | These routes are protected because only logged-in users/admin should see
    | messages submitted by website visitors.
    */
    Route::controller(ContactMessageController::class)->group(function () {
        Route::get('contact-messages', 'index');
        Route::get('contact-messages/{contactMessage}', 'show');
        Route::patch('contact-messages/{contactMessage}/read', 'markAsRead');
        Route::patch('contact-messages/{contactMessage}/replied', 'markAsReplied');
        Route::delete('contact-messages/{contactMessage}', 'destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Support AI Knowledge / Training Management
    |--------------------------------------------------------------------------
    | Admin can train the chatbot from dashboard.
    */
    Route::controller(SupportAiController::class)->group(function () {
        Route::get('support-ai/knowledge', 'indexKnowledge');
        Route::post('support-ai/knowledge', 'storeKnowledge');
        Route::get('support-ai/knowledge/{knowledge}', 'showKnowledge');
        Route::put('support-ai/knowledge/{knowledge}', 'updateKnowledge');
        Route::patch('support-ai/knowledge/{knowledge}', 'updateKnowledge');
        Route::delete('support-ai/knowledge/{knowledge}', 'destroyKnowledge');

        /*
        |--------------------------------------------------------------------------
        | Support AI Chat Sessions
        |--------------------------------------------------------------------------
        | Admin can review chatbot conversations.
        */
        Route::get('support-ai/sessions', 'sessions');
        Route::get('support-ai/sessions/{session}', 'sessionMessages');
        Route::patch('support-ai/sessions/{session}/close', 'closeSession');
    });

    /*
    |--------------------------------------------------------------------------
    | Tasks
    |--------------------------------------------------------------------------
    */
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
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\TargetController;
use App\Http\Controllers\API\SalaryController;
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
| Public website routes
|--------------------------------------------------------------------------
*/

Route::post(
    'contact-messages',
    [ContactMessageController::class, 'store']
);

Route::post(
    'support-ai/chat',
    [SupportAiController::class, 'chat']
);

Route::get(
    'monthly-plan-page',
    [MonthlyPlanPageController::class, 'show']
);

/*
|--------------------------------------------------------------------------
| Public property image route
|--------------------------------------------------------------------------
|
| Property cards use normal <img> requests, which cannot attach the Bearer
| token stored by the dashboard. Only the image file is public; property
| management and property data routes remain protected by Sanctum.
|
| Keep this route before Route::apiResource('properties', ...).
*/

Route::get(
    'property-images/{property}',
    [PropertyController::class, 'image']
)->whereNumber('property');

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
    | Monthly employee targets
    |--------------------------------------------------------------------------
    */

    Route::controller(TargetController::class)->group(function () {
        Route::get('targets/monthly-scores', 'index');

        Route::post('targets', 'store');

        Route::get('targets/{target}', 'show');
        Route::put('targets/{target}', 'update');
        Route::patch('targets/{target}', 'update');
        Route::delete('targets/{target}', 'destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Employee salaries
    |--------------------------------------------------------------------------
    */

    Route::controller(SalaryController::class)->group(function () {
        Route::get('salaries/monthly-calculations', 'index');

        Route::post('salaries', 'store');

        Route::get('salaries/{salary}', 'show');
        Route::put('salaries/{salary}', 'update');
        Route::patch('salaries/{salary}', 'update');
        Route::delete('salaries/{salary}', 'destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Monthly Plan Page Management
    |--------------------------------------------------------------------------
    */

    Route::post(
        'monthly-plan-page',
        [MonthlyPlanPageController::class, 'storeOrUpdate']
    );

    /*
    |--------------------------------------------------------------------------
    | Contact messages management
    |--------------------------------------------------------------------------
    */

    Route::controller(ContactMessageController::class)->group(function () {
        Route::get('contact-messages', 'index');
        Route::get('contact-messages/{contactMessage}', 'show');
        Route::patch(
            'contact-messages/{contactMessage}/read',
            'markAsRead'
        );
        Route::patch(
            'contact-messages/{contactMessage}/replied',
            'markAsReplied'
        );
        Route::delete(
            'contact-messages/{contactMessage}',
            'destroy'
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Support AI management
    |--------------------------------------------------------------------------
    */

    Route::controller(SupportAiController::class)->group(function () {
        Route::get('support-ai/knowledge', 'indexKnowledge');
        Route::post('support-ai/knowledge', 'storeKnowledge');
        Route::get(
            'support-ai/knowledge/{knowledge}',
            'showKnowledge'
        );
        Route::put(
            'support-ai/knowledge/{knowledge}',
            'updateKnowledge'
        );
        Route::patch(
            'support-ai/knowledge/{knowledge}',
            'updateKnowledge'
        );
        Route::delete(
            'support-ai/knowledge/{knowledge}',
            'destroyKnowledge'
        );

        Route::get('support-ai/sessions', 'sessions');
        Route::get(
            'support-ai/sessions/{session}',
            'sessionMessages'
        );
        Route::patch(
            'support-ai/sessions/{session}/close',
            'closeSession'
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Tasks
    |--------------------------------------------------------------------------
    */

    Route::controller(TaskController::class)->group(function () {
        Route::get('tasks', 'index');
        Route::post('tasks', 'store');

        /*
         * Specific routes must stay before tasks/{task}.
         */
        Route::post(
            'tasks/ai-organize',
            'organizeTaskWithGemini'
        )->middleware('throttle:20,1');

        Route::get('tasks/weekly-report', 'weeklyReport');
        Route::post(
            'tasks/report-cache/rebuild',
            'rebuildTaskReportCache'
        );
        Route::get('my-tasks', 'myTasks');

        Route::post(
            'tasks/{task}/assign-workers',
            'assignWorkers'
        );
        Route::post(
            'tasks/{task}/sync-workers',
            'syncWorkers'
        );

        Route::get('tasks/{task}/rewards', 'rewards');
        Route::post('tasks/{task}/reward', 'saveReward');
        Route::post('tasks/{task}/rewards', 'saveReward');

        Route::get('tasks/{task}', 'show');
        Route::put('tasks/{task}', 'update');
        Route::patch('tasks/{task}', 'update');
        Route::delete('tasks/{task}', 'destroy');
    });
});
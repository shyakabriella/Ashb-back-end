<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\InvoiceController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\TargetController;
use App\Http\Controllers\API\SalaryController;
use App\Http\Controllers\API\ContractController;
use App\Http\Controllers\API\ExpenseController;
use App\Http\Controllers\API\RequestController;
use App\Http\Controllers\API\ContactMessageController;
use App\Http\Controllers\API\SupportAiController;
use App\Http\Controllers\API\MonthlyPlanPageController;

/*
|--------------------------------------------------------------------------
| Public authentication routes
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
    | Role management
    |--------------------------------------------------------------------------
    */

    Route::controller(RoleController::class)->group(function () {
        Route::get('roles', 'index');
        Route::post('roles', 'store');

        Route::patch(
            'roles/{role}/status',
            'updateStatus'
        )->whereNumber('role');

        Route::get(
            'roles/{role}',
            'show'
        )->whereNumber('role');

        Route::put(
            'roles/{role}',
            'update'
        )->whereNumber('role');

        Route::patch(
            'roles/{role}',
            'update'
        )->whereNumber('role');

        Route::delete(
            'roles/{role}',
            'destroy'
        )->whereNumber('role');
    });

    /*
    |--------------------------------------------------------------------------
    | User management
    |--------------------------------------------------------------------------
    */

    Route::controller(RegisterController::class)->group(function () {
        Route::get('users', 'users');
        Route::post('register', 'register');

        Route::get(
            'users/{user}',
            'showUser'
        )->whereNumber('user');

        Route::put(
            'users/{user}',
            'updateUser'
        )->whereNumber('user');

        Route::patch(
            'users/{user}',
            'updateUser'
        )->whereNumber('user');

        Route::delete(
            'users/{user}',
            'destroyUser'
        )->whereNumber('user');

        Route::get(
            'users/{user}/profile',
            'showUser'
        )->whereNumber('user');

        Route::post(
            'users/{user}/profile',
            'updateUser'
        )->whereNumber('user');

        Route::patch(
            'users/{user}/profile',
            'updateUser'
        )->whereNumber('user');
    });

    /*
    |--------------------------------------------------------------------------
    | Invoice management
    |--------------------------------------------------------------------------
    | These routes must stay above Route::apiResource('properties').
    | The payment page keeps showing property list, but these routes allow:
    | - Push invoice manually
    | - Mark property invoice as paid manually
    | - View saved invoice records when needed
    |--------------------------------------------------------------------------
    */

    Route::post(
        'properties/{property}/push-invoice',
        [InvoiceController::class, 'pushPropertyInvoice']
    )->whereNumber('property');

    Route::post(
        'properties/{property}/mark-paid',
        [InvoiceController::class, 'markPropertyPaid']
    )->whereNumber('property');

    Route::patch(
        'properties/{property}/mark-paid',
        [InvoiceController::class, 'markPropertyPaid']
    )->whereNumber('property');

    Route::get(
        'invoices/summary',
        [InvoiceController::class, 'summary']
    );

    Route::get(
        'invoices',
        [InvoiceController::class, 'index']
    );

    Route::post(
        'invoices/{invoice}/mark-paid',
        [InvoiceController::class, 'markPaid']
    )->whereNumber('invoice');

    Route::patch(
        'invoices/{invoice}/mark-paid',
        [InvoiceController::class, 'markPaid']
    )->whereNumber('invoice');

    Route::get(
        'invoices/{invoice}',
        [InvoiceController::class, 'show']
    )->whereNumber('invoice');

    /*
    |--------------------------------------------------------------------------
    | Property management
    |--------------------------------------------------------------------------
    | Specific property routes must stay above Route::apiResource('properties').
    |--------------------------------------------------------------------------
    */

    Route::get(
        'properties/monthly-finance',
        [PropertyController::class, 'monthlyFinance']
    );

    Route::apiResource(
        'properties',
        PropertyController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Client management
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'clients',
        ClientController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Contract management
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'contracts',
        ContractController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Request management
    |--------------------------------------------------------------------------
    | When a request is approved, it automatically creates an expense.
    | Specific request routes must stay above Route::apiResource('requests').
    |--------------------------------------------------------------------------
    */

    Route::get(
        'requests/references',
        [RequestController::class, 'references']
    );

    Route::post(
        'requests/{businessRequest}/approve',
        [RequestController::class, 'approve']
    )->whereNumber('businessRequest');

    Route::post(
        'requests/{businessRequest}/reject',
        [RequestController::class, 'reject']
    )->whereNumber('businessRequest');

    Route::apiResource(
        'requests',
        RequestController::class
    )->parameters([
        'requests' => 'businessRequest',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Expense management
    |--------------------------------------------------------------------------
    | Specific expense routes must remain above Route::apiResource('expenses').
    |--------------------------------------------------------------------------
    */

    Route::post(
        'expenses/generate-description',
        [ExpenseController::class, 'generateDescription']
    )->middleware('throttle:20,1');

    Route::post(
        'expenses/{expense}/generate-preview',
        [ExpenseController::class, 'generatePreview']
    )->whereNumber('expense')->middleware('throttle:20,1');

    Route::get(
        'expenses/summary',
        [ExpenseController::class, 'summary']
    );

    Route::apiResource(
        'expenses',
        ExpenseController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Monthly employee targets
    |--------------------------------------------------------------------------
    */

    Route::controller(TargetController::class)->group(function () {
        Route::get(
            'targets/monthly-scores',
            'index'
        );

        Route::post(
            'targets',
            'store'
        );

        Route::get(
            'targets/{target}',
            'show'
        )->whereNumber('target');

        Route::put(
            'targets/{target}',
            'update'
        )->whereNumber('target');

        Route::patch(
            'targets/{target}',
            'update'
        )->whereNumber('target');

        Route::delete(
            'targets/{target}',
            'destroy'
        )->whereNumber('target');
    });

    /*
    |--------------------------------------------------------------------------
    | Employee salaries
    |--------------------------------------------------------------------------
    */

    Route::controller(SalaryController::class)->group(function () {
        Route::get(
            'salaries/monthly-calculations',
            'index'
        );

        Route::post(
            'salaries',
            'store'
        );

        Route::get(
            'salaries/{salary}',
            'show'
        )->whereNumber('salary');

        Route::put(
            'salaries/{salary}',
            'update'
        )->whereNumber('salary');

        Route::patch(
            'salaries/{salary}',
            'update'
        )->whereNumber('salary');

        Route::delete(
            'salaries/{salary}',
            'destroy'
        )->whereNumber('salary');
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
        Route::get(
            'contact-messages',
            'index'
        );

        Route::get(
            'contact-messages/{contactMessage}',
            'show'
        )->whereNumber('contactMessage');

        Route::patch(
            'contact-messages/{contactMessage}/read',
            'markAsRead'
        )->whereNumber('contactMessage');

        Route::patch(
            'contact-messages/{contactMessage}/replied',
            'markAsReplied'
        )->whereNumber('contactMessage');

        Route::delete(
            'contact-messages/{contactMessage}',
            'destroy'
        )->whereNumber('contactMessage');
    });

    /*
    |--------------------------------------------------------------------------
    | Support AI management
    |--------------------------------------------------------------------------
    */

    Route::controller(SupportAiController::class)->group(function () {
        Route::get(
            'support-ai/knowledge',
            'indexKnowledge'
        );

        Route::post(
            'support-ai/knowledge',
            'storeKnowledge'
        );

        Route::get(
            'support-ai/knowledge/{knowledge}',
            'showKnowledge'
        )->whereNumber('knowledge');

        Route::put(
            'support-ai/knowledge/{knowledge}',
            'updateKnowledge'
        )->whereNumber('knowledge');

        Route::patch(
            'support-ai/knowledge/{knowledge}',
            'updateKnowledge'
        )->whereNumber('knowledge');

        Route::delete(
            'support-ai/knowledge/{knowledge}',
            'destroyKnowledge'
        )->whereNumber('knowledge');

        Route::get(
            'support-ai/sessions',
            'sessions'
        );

        Route::get(
            'support-ai/sessions/{session}',
            'sessionMessages'
        )->whereNumber('session');

        Route::patch(
            'support-ai/sessions/{session}/close',
            'closeSession'
        )->whereNumber('session');
    });

    /*
    |--------------------------------------------------------------------------
    | Task management
    |--------------------------------------------------------------------------
    */

    Route::controller(TaskController::class)->group(function () {
        Route::get(
            'tasks',
            'index'
        );

        Route::post(
            'tasks',
            'store'
        );

        Route::post(
            'tasks/ai-organize',
            'organizeTaskWithGemini'
        )->middleware('throttle:20,1');

        Route::get(
            'tasks/weekly-report',
            'weeklyReport'
        );

        Route::post(
            'tasks/report-cache/rebuild',
            'rebuildTaskReportCache'
        );

        Route::get(
            'my-tasks',
            'myTasks'
        );

        Route::post(
            'tasks/{task}/assign-workers',
            'assignWorkers'
        )->whereNumber('task');

        Route::post(
            'tasks/{task}/sync-workers',
            'syncWorkers'
        )->whereNumber('task');

        Route::get(
            'tasks/{task}/rewards',
            'rewards'
        )->whereNumber('task');

        Route::post(
            'tasks/{task}/reward',
            'saveReward'
        )->whereNumber('task');

        Route::post(
            'tasks/{task}/rewards',
            'saveReward'
        )->whereNumber('task');

        Route::get(
            'tasks/{task}',
            'show'
        )->whereNumber('task');

        Route::put(
            'tasks/{task}',
            'update'
        )->whereNumber('task');

        Route::patch(
            'tasks/{task}',
            'update'
        )->whereNumber('task');

        Route::delete(
            'tasks/{task}',
            'destroy'
        )->whereNumber('task');
    });
});
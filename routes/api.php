<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\TargetController;
use App\Http\Controllers\API\SalaryController;
use App\Http\Controllers\API\ContractController;
use App\Http\Controllers\API\ExpenseController;
use App\Http\Controllers\API\ContactMessageController;
use App\Http\Controllers\API\SupportAiController;
use App\Http\Controllers\API\MonthlyPlanPageController;

// ========== CORE SOLUTIONS - CONTROLLERS (OLD - Deprecated) ==========
use App\Http\Controllers\home_pages\homepage_section_1_core_solution\Section1_OTAS_DistributionController;
use App\Http\Controllers\home_pages\homepage_section_1_core_solution\Section1_ChannelManagerController;
use App\Http\Controllers\home_pages\homepage_section_1_core_solution\Section1_PMS_MarketingController;
use App\Http\Controllers\home_pages\homepage_section_1_core_solution\Section1_AI_IntegrationController;
use App\Http\Controllers\home_pages\homepage_section_1_core_solution\Section1_WebsiteBookingEngineController;

// ========== NEW CONTROLLERS ==========
use App\Http\Controllers\Api\home_pages\HeroSectionController;
use App\Http\Controllers\Api\home_pages\Section3Controller;
use App\Http\Controllers\Api\home_pages\OTASDistributionPageController;
use App\Http\Controllers\Api\home_pages\ChannelManagerPageController;
use App\Http\Controllers\Api\home_pages\Section4Controller;

/*
|--------------------------------------------------------------------------
| Public authentication routes
|--------------------------------------------------------------------------
*/

// Auth routes
Route::controller(RegisterController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
});

// Contact / Support AI / Monthly Plan (public)
Route::post('contact-messages', [ContactMessageController::class, 'store']);
Route::post('support-ai/chat', [SupportAiController::class, 'chat']);
Route::get('monthly-plan-page', [MonthlyPlanPageController::class, 'show']);

// Property image (public — <img> tags can't send Bearer tokens)
Route::get('property-images/{property}', [PropertyController::class, 'image'])->whereNumber('property');

// ========== CORE SOLUTIONS - PUBLIC GET ENDPOINTS (OLD - Deprecated) ==========
Route::prefix('homepage/section1')->group(function () {
    Route::get('otas-distribution', [Section1_OTAS_DistributionController::class, 'index']);
    Route::get('otas-distribution/{id}', [Section1_OTAS_DistributionController::class, 'show']);
    Route::get('channel-manager', [Section1_ChannelManagerController::class, 'index']);
    Route::get('channel-manager/{id}', [Section1_ChannelManagerController::class, 'show']);
    Route::get('pms-marketing', [Section1_PMS_MarketingController::class, 'index']);
    Route::get('pms-marketing/{id}', [Section1_PMS_MarketingController::class, 'show']);
    Route::get('ai-integration', [Section1_AI_IntegrationController::class, 'index']);
    Route::get('ai-integration/{id}', [Section1_AI_IntegrationController::class, 'show']);
    Route::get('website-booking-engine', [Section1_WebsiteBookingEngineController::class, 'index']);
    Route::get('website-booking-engine/{id}', [Section1_WebsiteBookingEngineController::class, 'show']);
});

// ========== HERO SECTION - PUBLIC GET ==========
Route::prefix('cms/home-pages')->group(function () {
    Route::get('/hero', [HeroSectionController::class, 'index']);
});

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
|
*/
// ========== SECTION 3 & 4 - PUBLIC GET ==========
Route::get('/section3', [Section3Controller::class, 'index']);
Route::get('/section4', [Section4Controller::class, 'index']);

// ========== OTAs DISTRIBUTION PAGE - PUBLIC GET ==========
Route::prefix('otas')->group(function () {
    Route::get('/hero', [OTASDistributionPageController::class, 'getHero']);
    Route::get('/platforms', [OTASDistributionPageController::class, 'getPlatforms']);
    Route::get('/why-choose', [OTASDistributionPageController::class, 'getWhyChoose']);
    Route::get('/cta', [OTASDistributionPageController::class, 'getCta']);
});

// ========== CHANNEL MANAGER PAGE - PUBLIC GET ==========
Route::prefix('channel-manager')->group(function () {
    Route::get('/', [ChannelManagerPageController::class, 'index']);
    Route::get('/hero', [ChannelManagerPageController::class, 'getHero']);
    Route::get('/dashboard-stats', [ChannelManagerPageController::class, 'getDashboardStats']);
    Route::get('/sync-cards', [ChannelManagerPageController::class, 'getSyncCards']);
    Route::get('/zero-errors', [ChannelManagerPageController::class, 'getZeroErrors']);
    Route::get('/stats-items', [ChannelManagerPageController::class, 'getStatsItems']);
    Route::get('/sync-engine', [ChannelManagerPageController::class, 'getSyncEngine']);
    Route::get('/footer', [ChannelManagerPageController::class, 'getFooterCta']);
});

/*
|--------------------------------------------------------------------------
| Protected routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Profile & User Management
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
    |
    | Role creation, updating and deletion permissions are also checked
    | inside RoleController.
    |
    */

    Route::controller(RoleController::class)->group(function () {
        Route::get('roles', 'index');
        Route::post('roles', 'store');

        /*
         * The status route must stay before roles/{role}.
         */

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
    Route::controller(RegisterController::class)->group(function () {
        Route::get('roles', 'roles');
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
    | Property management
    |--------------------------------------------------------------------------
    */

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
    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('contracts', ContractController::class);

    /*
    |--------------------------------------------------------------------------
    | Expenses
    |--------------------------------------------------------------------------
    */

    // summary must come before the resource to avoid being matched as an ID
    Route::get('expenses/summary', [ExpenseController::class, 'summary']);
    Route::apiResource('expenses', ExpenseController::class);

    /*
    |--------------------------------------------------------------------------
    | Targets & Salaries
    |--------------------------------------------------------------------------
    |
    | Specific routes must remain before targets/{target}.
    |
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
    |
    | Specific routes must remain before salaries/{salary}.
    |
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
        Route::get('targets/monthly-scores', 'index');
        Route::post('targets', 'store');
        Route::get('targets/{target}', 'show');
        Route::put('targets/{target}', 'update');
        Route::patch('targets/{target}', 'update');
        Route::delete('targets/{target}', 'destroy');
    });

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
    | Monthly Plan Page
    |--------------------------------------------------------------------------
    */

    Route::post('monthly-plan-page', [MonthlyPlanPageController::class, 'storeOrUpdate']);

    /*
    |--------------------------------------------------------------------------
    | Contact Messages
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
        Route::get('contact-messages', 'index');
        Route::get('contact-messages/{contactMessage}', 'show');
        Route::patch('contact-messages/{contactMessage}/read', 'markAsRead');
        Route::patch('contact-messages/{contactMessage}/replied', 'markAsReplied');
        Route::delete('contact-messages/{contactMessage}', 'destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Support AI
    |--------------------------------------------------------------------------
    */

    Route::controller(SupportAiController::class)->group(function () {
        /*
         * Knowledge management
         */

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

        /*
         * AI support sessions
         */

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
        Route::get('support-ai/knowledge', 'indexKnowledge');
        Route::post('support-ai/knowledge', 'storeKnowledge');
        Route::get('support-ai/knowledge/{knowledge}', 'showKnowledge');
        Route::put('support-ai/knowledge/{knowledge}', 'updateKnowledge');
        Route::patch('support-ai/knowledge/{knowledge}', 'updateKnowledge');
        Route::delete('support-ai/knowledge/{knowledge}', 'destroyKnowledge');

        Route::get('support-ai/sessions', 'sessions');
        Route::get('support-ai/sessions/{session}', 'sessionMessages');
        Route::patch('support-ai/sessions/{session}/close', 'closeSession');
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

        /*
         * Specific task routes must stay before tasks/{task}.
         */
        // Specific routes MUST stay above tasks/{task}
        Route::post('tasks/ai-organize', 'organizeTaskWithGemini')->middleware('throttle:20,1');
        Route::get('tasks/weekly-report', 'weeklyReport');
        Route::post('tasks/report-cache/rebuild', 'rebuildTaskReportCache');
        Route::get('my-tasks', 'myTasks');

        Route::post('tasks/{task}/assign-workers', 'assignWorkers');
        Route::post('tasks/{task}/sync-workers', 'syncWorkers');
        Route::get('tasks/{task}/rewards', 'rewards');
        Route::post('tasks/{task}/reward', 'saveReward');
        Route::post('tasks/{task}/rewards', 'saveReward');

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
        Route::get('tasks/{task}', 'show');
        Route::put('tasks/{task}', 'update');
        Route::patch('tasks/{task}', 'update');
        Route::delete('tasks/{task}', 'destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | CMS — Homepage / Section routes (OLD deprecated + new)
    |--------------------------------------------------------------------------
    */

    // OTAS Distribution (old)
    Route::post('homepage/section1/otas-distribution', [Section1_OTAS_DistributionController::class, 'store']);
    Route::put('homepage/section1/otas-distribution/{id}', [Section1_OTAS_DistributionController::class, 'update']);
    Route::delete('homepage/section1/otas-distribution/{id}', [Section1_OTAS_DistributionController::class, 'destroy']);

    // Channel Manager (old)
    Route::post('homepage/section1/channel-manager', [Section1_ChannelManagerController::class, 'store']);
    Route::put('homepage/section1/channel-manager/{id}', [Section1_ChannelManagerController::class, 'update']);
    Route::delete('homepage/section1/channel-manager/{id}', [Section1_ChannelManagerController::class, 'destroy']);

    // PMS Marketing (old)
    Route::post('homepage/section1/pms-marketing', [Section1_PMS_MarketingController::class, 'store']);
    Route::put('homepage/section1/pms-marketing/{id}', [Section1_PMS_MarketingController::class, 'update']);
    Route::delete('homepage/section1/pms-marketing/{id}', [Section1_PMS_MarketingController::class, 'destroy']);

    // AI Integration (old)
    Route::post('homepage/section1/ai-integration', [Section1_AI_IntegrationController::class, 'store']);
    Route::put('homepage/section1/ai-integration/{id}', [Section1_AI_IntegrationController::class, 'update']);
    Route::delete('homepage/section1/ai-integration/{id}', [Section1_AI_IntegrationController::class, 'destroy']);

    // Website Booking Engine (old)
    Route::post('homepage/section1/website-booking-engine', [Section1_WebsiteBookingEngineController::class, 'store']);
    Route::put('homepage/section1/website-booking-engine/{id}', [Section1_WebsiteBookingEngineController::class, 'update']);
    Route::delete('homepage/section1/website-booking-engine/{id}', [Section1_WebsiteBookingEngineController::class, 'destroy']);

    // Hero Section (new)
    Route::prefix('cms/home-pages')->group(function () {
        Route::post('/hero', [HeroSectionController::class, 'store']);
        Route::put('/hero/{id}', [HeroSectionController::class, 'update']);
        Route::delete('/hero/{id}', [HeroSectionController::class, 'destroy']);
    });

    // Section 3 (new)
    Route::post('/section3/update', [Section3Controller::class, 'update']);
    Route::delete('/section3/reset', [Section3Controller::class, 'destroy']);
    Route::delete('/section3/image', [Section3Controller::class, 'deleteImage']);

    // Section 4 (new)
    Route::post('/section4/update', [Section4Controller::class, 'update']);

    // OTAs Distribution Page (new)
    Route::prefix('otas')->group(function () {
        Route::post('/hero', [OTASDistributionPageController::class, 'createHero']);
        Route::put('/hero', [OTASDistributionPageController::class, 'updateHero']);
        Route::delete('/hero', [OTASDistributionPageController::class, 'deleteHero']);

        Route::post('/platforms', [OTASDistributionPageController::class, 'createPlatform']);
        Route::post('/platforms/section-title', [OTASDistributionPageController::class, 'updatePlatformsSectionTitle']);
        Route::put('/platforms/{id}', [OTASDistributionPageController::class, 'updatePlatform']);
        Route::delete('/platforms/{id}', [OTASDistributionPageController::class, 'deletePlatform']);

        Route::post('/why-choose/items', [OTASDistributionPageController::class, 'createWhyChooseItem']);
        Route::post('/why-choose/section', [OTASDistributionPageController::class, 'updateWhyChooseSection']);
        Route::put('/why-choose/items/{id}', [OTASDistributionPageController::class, 'updateWhyChooseItem']);
        Route::delete('/why-choose/items/{id}', [OTASDistributionPageController::class, 'deleteWhyChooseItem']);

        Route::post('/cta', [OTASDistributionPageController::class, 'createCta']);
        Route::put('/cta', [OTASDistributionPageController::class, 'updateCta']);
        Route::delete('/cta', [OTASDistributionPageController::class, 'deleteCta']);
    });

    // Channel Manager Page (new)
    Route::prefix('channel-manager')->group(function () {
        Route::put('/hero', [ChannelManagerPageController::class, 'updateHero']);
        Route::delete('/hero', [ChannelManagerPageController::class, 'deleteHero']);
        Route::put('/dashboard-stats', [ChannelManagerPageController::class, 'updateDashboardStats']);
        Route::put('/sync-cards', [ChannelManagerPageController::class, 'updateSyncCards']);
        Route::put('/zero-errors', [ChannelManagerPageController::class, 'updateZeroErrors']);
        Route::put('/stats-items', [ChannelManagerPageController::class, 'updateStatsItems']);
        Route::put('/sync-engine', [ChannelManagerPageController::class, 'updateSyncEngine']);
        Route::put('/footer', [ChannelManagerPageController::class, 'updateFooterCta']);
    });
});


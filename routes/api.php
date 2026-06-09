<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\ContractController;

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

// ========== SECTION 4 CONTROLLER ==========
use App\Http\Controllers\Api\home_pages\Section4Controller;

// ========== PUBLIC ROUTES (No authentication required) ==========

// Auth routes
Route::controller(RegisterController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
});

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

// ========== SECTION 3 - PUBLIC GET ==========
Route::get('/section3', [Section3Controller::class, 'index']);

// ========== SECTION 4 - PUBLIC GET ==========
Route::get('/section4', [Section4Controller::class, 'index']);

// ========== OTAs DISTRIBUTION PAGE - PUBLIC GET ENDPOINTS ==========
Route::prefix('otas')->group(function () {
    Route::get('/hero', [OTASDistributionPageController::class, 'getHero']);
    Route::get('/platforms', [OTASDistributionPageController::class, 'getPlatforms']);
    Route::get('/why-choose', [OTASDistributionPageController::class, 'getWhyChoose']);
    Route::get('/cta', [OTASDistributionPageController::class, 'getCta']);
});

// ========== CHANNEL MANAGER PAGE - PUBLIC GET ENDPOINTS ==========
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

// ========== PROTECTED ROUTES (Authentication required) ==========
Route::middleware('auth:sanctum')->group(function () {
    
    // ========== USER MANAGEMENT ==========
    Route::controller(RegisterController::class)->group(function () {
        Route::get('me', 'me');
        Route::get('roles', 'roles');
        Route::get('users', 'users');
        Route::post('register', 'register');
    });

    // ========== PROFILE MANAGEMENT ==========
    Route::controller(ProfileController::class)->group(function () {
        Route::get('profile', 'show');
        Route::post('profile', 'update');
    });

    // ========== CRUD RESOURCES ==========
    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('contracts', ContractController::class);

    // ========== TASK MANAGEMENT ==========
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
        Route::delete('tasks/{task}', 'destroy');
    });

    // ========== CORE SOLUTIONS - PROTECTED CRUD (OLD - Deprecated) ==========
    // OTAS Distribution
    Route::post('homepage/section1/otas-distribution', [Section1_OTAS_DistributionController::class, 'store']);
    Route::put('homepage/section1/otas-distribution/{id}', [Section1_OTAS_DistributionController::class, 'update']);
    Route::delete('homepage/section1/otas-distribution/{id}', [Section1_OTAS_DistributionController::class, 'destroy']);
    
    // Channel Manager
    Route::post('homepage/section1/channel-manager', [Section1_ChannelManagerController::class, 'store']);
    Route::put('homepage/section1/channel-manager/{id}', [Section1_ChannelManagerController::class, 'update']);
    Route::delete('homepage/section1/channel-manager/{id}', [Section1_ChannelManagerController::class, 'destroy']);
    
    // PMS Marketing
    Route::post('homepage/section1/pms-marketing', [Section1_PMS_MarketingController::class, 'store']);
    Route::put('homepage/section1/pms-marketing/{id}', [Section1_PMS_MarketingController::class, 'update']);
    Route::delete('homepage/section1/pms-marketing/{id}', [Section1_PMS_MarketingController::class, 'destroy']);
    
    // AI Integration
    Route::post('homepage/section1/ai-integration', [Section1_AI_IntegrationController::class, 'store']);
    Route::put('homepage/section1/ai-integration/{id}', [Section1_AI_IntegrationController::class, 'update']);
    Route::delete('homepage/section1/ai-integration/{id}', [Section1_AI_IntegrationController::class, 'destroy']);
    
    // Website Booking Engine
    Route::post('homepage/section1/website-booking-engine', [Section1_WebsiteBookingEngineController::class, 'store']);
    Route::put('homepage/section1/website-booking-engine/{id}', [Section1_WebsiteBookingEngineController::class, 'update']);
    Route::delete('homepage/section1/website-booking-engine/{id}', [Section1_WebsiteBookingEngineController::class, 'destroy']);
    
    // ========== HERO SECTION - PROTECTED CRUD ==========
    Route::prefix('cms/home-pages')->group(function () {
        Route::post('/hero', [HeroSectionController::class, 'store']);
        Route::put('/hero/{id}', [HeroSectionController::class, 'update']);
        Route::delete('/hero/{id}', [HeroSectionController::class, 'destroy']);
    });

    // ========== SECTION 3 - PROTECTED CRUD ==========
    Route::post('/section3/update', [Section3Controller::class, 'update']);
    Route::delete('/section3/reset', [Section3Controller::class, 'destroy']);
    Route::delete('/section3/image', [Section3Controller::class, 'deleteImage']);
    
    // ========== SECTION 4 - PROTECTED CRUD ==========
    Route::post('/section4/update', [Section4Controller::class, 'update']);
    
    // ========== OTAs DISTRIBUTION PAGE - PROTECTED CRUD ==========
    Route::prefix('otas')->group(function () {
        // Hero Section
        Route::post('/hero', [OTASDistributionPageController::class, 'createHero']);
        Route::put('/hero', [OTASDistributionPageController::class, 'updateHero']);
        Route::delete('/hero', [OTASDistributionPageController::class, 'deleteHero']);
        
        // Platforms Section
        Route::post('/platforms', [OTASDistributionPageController::class, 'createPlatform']);
        Route::post('/platforms/section-title', [OTASDistributionPageController::class, 'updatePlatformsSectionTitle']);
        Route::put('/platforms/{id}', [OTASDistributionPageController::class, 'updatePlatform']);
        Route::delete('/platforms/{id}', [OTASDistributionPageController::class, 'deletePlatform']);
        
        // Why Choose Us Section
        Route::post('/why-choose/items', [OTASDistributionPageController::class, 'createWhyChooseItem']);
        Route::post('/why-choose/section', [OTASDistributionPageController::class, 'updateWhyChooseSection']);
        Route::put('/why-choose/items/{id}', [OTASDistributionPageController::class, 'updateWhyChooseItem']);
        Route::delete('/why-choose/items/{id}', [OTASDistributionPageController::class, 'deleteWhyChooseItem']);
        
        // CTA Banner
        Route::post('/cta', [OTASDistributionPageController::class, 'createCta']);
        Route::put('/cta', [OTASDistributionPageController::class, 'updateCta']);
        Route::delete('/cta', [OTASDistributionPageController::class, 'deleteCta']);
    });
    
    // ========== CHANNEL MANAGER PAGE - PROTECTED CRUD ==========
    Route::prefix('channel-manager')->group(function () {
        // Hero Section
        Route::put('/hero', [ChannelManagerPageController::class, 'updateHero']);
        Route::delete('/hero', [ChannelManagerPageController::class, 'deleteHero']);
        
        // Dashboard Stats
        Route::put('/dashboard-stats', [ChannelManagerPageController::class, 'updateDashboardStats']);
        
        // Sync Cards
        Route::put('/sync-cards', [ChannelManagerPageController::class, 'updateSyncCards']);
        
        // Zero Errors Section
        Route::put('/zero-errors', [ChannelManagerPageController::class, 'updateZeroErrors']);
        
        // Stats Items
        Route::put('/stats-items', [ChannelManagerPageController::class, 'updateStatsItems']);
        
        // Sync Engine Section
        Route::put('/sync-engine', [ChannelManagerPageController::class, 'updateSyncEngine']);
        
        // Footer CTA
        Route::put('/footer', [ChannelManagerPageController::class, 'updateFooterCta']);
    });
});

// ========== END OF API ROUTES ==========
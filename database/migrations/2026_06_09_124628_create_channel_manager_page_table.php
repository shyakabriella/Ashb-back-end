<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_manager_page', function (Blueprint $table) {
            $table->id();
            
            // ========== HERO SECTION ==========
            $table->string('hero_title')->nullable();
            $table->string('hero_subtitle')->nullable();
            $table->text('hero_description')->nullable();
            $table->string('hero_button_text')->nullable();
            $table->string('hero_image')->nullable();
            
            // ========== DASHBOARD STATS (Right side chart data) ==========
            $table->string('total_bookings')->nullable();
            $table->string('total_bookings_percentage')->nullable();
            $table->string('revenue')->nullable();
            $table->string('revenue_percentage')->nullable();
            $table->json('ota_status')->nullable(); // [{"name":"Booking.com","status":"Synced","time":"2s ago"}]
            $table->string('trust_count')->nullable();
            $table->string('trust_text')->nullable();
            
            // ========== SECTION 1: SYNC RATES (3 Cards) ==========
            $table->json('sync_cards')->nullable(); // 3 cards with title, value, description
            
            // ========== SECTION 2: ZERO ERRORS GUARANTEED ==========
            $table->string('zero_errors_title')->nullable();
            $table->string('zero_errors_subtitle')->nullable();
            $table->text('zero_errors_description')->nullable();
            $table->json('zero_errors_cards')->nullable(); // 3 cards
            
            // ========== SECTION 3: STATS SECTION ==========
            $table->json('stats_items')->nullable(); // 4 stats items
            
            // ========== SECTION 4: SYNC ENGINE WORKS ==========
            $table->string('sync_engine_title')->nullable();
            $table->string('sync_engine_subtitle')->nullable();
            $table->text('sync_engine_description')->nullable();
            $table->json('sync_engine_steps')->nullable(); // 3 steps
            $table->string('sync_engine_image')->nullable();
            
            // ========== FOOTER CTA ==========
            $table->string('footer_title')->nullable();
            $table->text('footer_description')->nullable();
            $table->string('footer_button_text')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_manager_page');
    }
};
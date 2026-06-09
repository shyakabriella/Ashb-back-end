<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otas_distribution_page', function (Blueprint $table) {
            $table->id();
            
            // ========== HERO SECTION ==========
            $table->string('hero_title')->nullable();
            $table->string('hero_subtitle')->nullable();
            $table->text('hero_description')->nullable();
            $table->string('hero_button1_text')->nullable();
            $table->string('hero_button2_text')->nullable();
            $table->string('hero_image')->nullable();
            
            // ========== SECTION 1: PLATFORMS ==========
            $table->string('platforms_section_title')->nullable();
            $table->json('platforms')->nullable(); // Stores array of platforms
            
            // ========== SECTION 2: WHY CHOOSE US ==========
            $table->string('why_choose_section_title')->nullable();
            $table->text('why_choose_section_description')->nullable();
            $table->json('why_choose_items')->nullable(); // Stores array of items
            
            // ========== SECTION 3: CTA BANNER ==========
            $table->string('cta_title')->nullable();
            $table->text('cta_description')->nullable();
            $table->string('cta_button_text')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otas_distribution_page');
    }
};
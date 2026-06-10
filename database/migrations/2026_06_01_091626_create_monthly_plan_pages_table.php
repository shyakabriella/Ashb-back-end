<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monthly_plan_pages', function (Blueprint $table) {
            $table->id();

            $table->string('page_key')->default('monthly-plans')->unique();

            // Hero section
            $table->string('hero_kicker')->nullable();
            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();

            // Pricing cards
            $table->json('tiers')->nullable();

            // Banner section
            $table->text('banner_image')->nullable();
            $table->string('banner_title')->nullable();
            $table->text('banner_subtitle')->nullable();

            // Compare section
            $table->string('compare_title')->nullable();
            $table->json('comparison_rows')->nullable();

            // FAQ section
            $table->string('faq_title')->nullable();
            $table->json('faqs')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_plan_pages');
    }
};
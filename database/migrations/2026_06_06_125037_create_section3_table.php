<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section3', function (Blueprint $table) {
            $table->id();
            
            // Left side content
            $table->text('left_title')->nullable();
            $table->text('left_description')->nullable();
            $table->string('left_image_url')->nullable();
            
            // Right side medium image (top)
            $table->string('right_medium_image_url')->nullable();
            
            // Right side items (3 items) - stored as JSON
            $table->json('right_items')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section3');
    }
};
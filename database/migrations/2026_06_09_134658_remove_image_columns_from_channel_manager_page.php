<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_manager_page', function (Blueprint $table) {
            // Remove hero image column if it exists
            if (Schema::hasColumn('channel_manager_page', 'hero_image')) {
                $table->dropColumn('hero_image');
            }
            
            // Remove sync engine image column if it exists
            if (Schema::hasColumn('channel_manager_page', 'sync_engine_image')) {
                $table->dropColumn('sync_engine_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('channel_manager_page', function (Blueprint $table) {
            $table->string('hero_image')->nullable()->after('hero_button_text');
            $table->string('sync_engine_image')->nullable()->after('sync_engine_steps');
        });
    }
};
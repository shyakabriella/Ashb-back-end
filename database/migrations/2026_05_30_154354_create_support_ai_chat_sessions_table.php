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
        Schema::create('support_ai_chat_sessions', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('visitor_hotel')->nullable();

            $table->string('source')->default('support_badge');
            $table->string('status')->default('open');
            // open, closed, transferred_to_human

            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('visitor_email');
            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ai_chat_sessions');
    }
};
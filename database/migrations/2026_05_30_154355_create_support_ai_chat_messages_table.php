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
        Schema::create('support_ai_chat_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('support_ai_chat_session_id')
                ->constrained('support_ai_chat_sessions')
                ->cascadeOnDelete();

            $table->string('sender')->default('user');
            // user, bot, admin

            $table->longText('message');

            $table->foreignId('matched_knowledge_id')
                ->nullable()
                ->constrained('support_ai_knowledge')
                ->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('sender');
            $table->index('matched_knowledge_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ai_chat_messages');
    }
};
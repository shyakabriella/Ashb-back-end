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
        Schema::create('support_ai_knowledge', function (Blueprint $table) {
            $table->id();

            $table->string('title')->nullable();
            $table->text('question');
            $table->longText('answer');

            // Example: ["hotel website", "booking engine", "digital marketing"]
            $table->json('keywords')->nullable();

            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ai_knowledge');
    }
};
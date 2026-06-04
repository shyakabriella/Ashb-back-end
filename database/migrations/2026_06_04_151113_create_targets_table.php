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
        Schema::create('targets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * Always store the first day of the month, for example 2026-06-01.
             */
            $table->date('target_month');

            /* Default company target: 30 tasks and 75% monthly score. */
            $table->unsignedInteger('minimum_tasks')->default(30);
            $table->decimal('target_percentage', 5, 2)->default(75.00);
            $table->decimal('maximum_score_per_task', 5, 2)->default(100.00);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'target_month']);
            $table->index('target_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
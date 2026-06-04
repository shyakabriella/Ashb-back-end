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
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * The salary starts applying from the first day of this month.
             * Example: 2026-06-01 applies to June 2026 and future months until
             * a newer salary record is created for the same worker.
             */
            $table->date('effective_from');

            $table->decimal('base_salary', 15, 2);
            $table->string('currency', 10)->default('RWF');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'effective_from']);
            $table->index(['effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
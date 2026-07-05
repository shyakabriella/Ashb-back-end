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
        Schema::create('task_report_caches', function (Blueprint $table) {
            $table->id();

            $table->string('report_type')->default('task_performance');
            $table->string('cache_key')->unique();

            /*
             * Scope examples:
             * - all-workers
             * - user:5
             */
            $table->string('scope')->default('all-workers');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('from_date');
            $table->date('to_date');

            /*
             * longText is used because report payload can contain many task rows.
             * Laravel model casts this field to array.
             */
            $table->longText('payload');

            $table->unsignedInteger('workers_count')->default(0);
            $table->unsignedInteger('tasks_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'from_date', 'to_date']);
            $table->index(['scope', 'from_date', 'to_date']);
            $table->index(['user_id', 'from_date', 'to_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_report_caches');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_rewards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('graded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('task_update_id')->nullable()->constrained('task_updates')->nullOnDelete();
            $table->foreignId('attachment_id')->nullable()->constrained('task_update_attachments')->nullOnDelete();

            $table->string('attachment_type')->nullable();
            $table->string('attachment_file_name')->nullable();
            $table->text('attachment_file_path')->nullable();

            $table->string('ranking');
            $table->string('ranking_label');
            $table->unsignedInteger('marks_percentage')->default(0);
            $table->string('grading')->nullable();

            $table->text('advice')->nullable();
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(
                ['task_id', 'recipient_user_id', 'task_update_id', 'attachment_id'],
                'task_rewards_unique_task_user_update_attachment'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_rewards');
    }
};
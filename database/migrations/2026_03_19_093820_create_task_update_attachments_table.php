<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_update_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_update_id')
                ->constrained('task_updates')
                ->cascadeOnDelete();

            $table->string('attachment_type', 30); // voice_note, image, video, document
            $table->string('disk', 50)->default('public');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->timestamps();

            $table->index(['task_update_id', 'attachment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_update_attachments');
    }
};
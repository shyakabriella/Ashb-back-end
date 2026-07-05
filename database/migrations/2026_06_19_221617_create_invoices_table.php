<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->nullOnDelete();

            $table->string('invoice_number')->unique();

            $table->string('property_name')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('property_email')->nullable();
            $table->string('manager_email')->nullable();

            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('RWF');

            $table->date('invoice_date');
            $table->date('due_date');

            $table->string('invoice_status')->default('issued');
            $table->string('payment_status')->default('unpaid');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->unsignedTinyInteger('last_reminder_days_before_due')->nullable();
            $table->unsignedSmallInteger('reminders_sent')->default(0);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['property_id', 'due_date']);
            $table->index(['invoice_status', 'payment_status', 'due_date']);
            $table->index('last_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
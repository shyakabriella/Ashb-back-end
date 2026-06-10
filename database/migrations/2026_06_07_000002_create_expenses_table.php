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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_code', 50)->unique();
            $table->date('expense_date');

            /*
             * employee_id is preferred when the employee exists in users.
             * employee_name keeps a readable snapshot and also supports
             * external employees who do not yet have a user account.
             */
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('employee_name')->nullable();

            $table->string('category', 100);
            $table->decimal('amount', 15, 2);
            $table->string('status', 30)->default('pending');

            /*
             * property_id is preferred. property_name is kept as a snapshot
             * and supports expenses that are not connected to a saved property.
             */
            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->nullOnDelete();
            $table->string('property_name')->nullable();

            $table->text('description')->nullable();
            $table->json('attachments')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['expense_date', 'status']);
            $table->index('category');
            $table->index('employee_id');
            $table->index('property_id');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
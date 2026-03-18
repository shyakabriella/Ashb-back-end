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
        Schema::create('client_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties');

            $table->string('property_name')->nullable();
            $table->string('booking_number')->nullable()->index();

            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('location')->nullable();
            $table->string('preferred_language')->nullable();

            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();

            $table->unsignedInteger('nights')->default(0);
            $table->unsignedInteger('total_guests')->default(0);
            $table->unsignedInteger('total_units')->default(0);

            $table->string('currency', 10)->default('USD');

            $table->decimal('total_price', 12, 2)->default(0);
            $table->decimal('commissionable_amount', 12, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);

            $table->string('arrival_time')->nullable();

            $table->string('pdf_original_name')->nullable();
            $table->string('stored_pdf_path')->nullable();

            $table->json('raw_payload')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_reservations');
    }
};
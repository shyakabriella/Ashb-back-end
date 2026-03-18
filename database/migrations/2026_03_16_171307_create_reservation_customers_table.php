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
        Schema::create('reservation_customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_reservation_id')
                ->constrained('client_reservations')
                ->cascadeOnDelete();

            $table->string('guest_name')->nullable();
            $table->string('room_label')->nullable();
            $table->string('room_type')->nullable();
            $table->string('occupancy')->nullable();
            $table->string('meal_plan')->nullable();

            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();

            $table->string('currency', 10)->default('USD');
            $table->decimal('price_per_night', 12, 2)->nullable();

            $table->text('rate_name')->nullable();
            $table->enum('source', ['pdf', 'manual'])->default('manual');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_customers');
    }
};
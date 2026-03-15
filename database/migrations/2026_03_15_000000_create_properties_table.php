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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();

            // Keeping href because your frontend uses it already
            $table->string('href')->nullable()->unique();

            // Use longText because frontend is sending base64 image data
            $table->longText('image')->nullable();

            // Property price is optional now
            $table->decimal('price', 12, 2)->nullable();

            $table->string('address');
            $table->string('location')->nullable();

            $table->unsignedInteger('units')->default(0);
            $table->unsignedTinyInteger('occupancy')->default(0);

            $table->enum('status', ['available', 'fully_booked', 'inactive'])->default('available');

            $table->text('description')->nullable();
            $table->boolean('is_favorite')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
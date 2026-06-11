
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section1_website_booking_engine', function (Blueprint $table) {
            $table->id();
            $table->string('icon_image');
            $table->string('title');
            $table->string('subtitle');
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section1_website_booking_engine');
    }
};
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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            $table->date('agreement_date');
            $table->date('effective_date');

            $table->string('client_name');
            $table->string('client_tin')->nullable();
            $table->string('hotel_name');
            $table->string('website_name')->nullable();

            $table->decimal('discount_percent', 8, 2)->nullable();
            $table->decimal('standard_monthly_fee', 15, 2)->default(0);
            $table->decimal('discounted_monthly_fee', 15, 2)->default(0);
            $table->decimal('post_discount_monthly_fee', 15, 2)->default(0);

            $table->string('provider_representative_name')->nullable();
            $table->string('provider_signature_text')->nullable();
            $table->date('provider_signed_date')->nullable();

            $table->string('client_representative_name')->nullable();
            $table->string('client_signature_text')->nullable();
            $table->date('client_signed_date')->nullable();

            $table->string('kpi_recipient')->nullable();

            $table->string('billing_cycle')->default('monthly');
            $table->unsignedTinyInteger('invoice_day')->default(1);
            $table->boolean('is_active')->default(true);

            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
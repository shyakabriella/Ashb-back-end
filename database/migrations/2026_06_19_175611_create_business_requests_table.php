<?php

use App\Models\Expense;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_requests', function (Blueprint $table) {
            $table->id();

            $table->string('request_code', 80)->unique();

            $table->foreignIdFor(Property::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('property_name')->nullable();

            $table->foreignIdFor(User::class, 'requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignIdFor(User::class, 'reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignIdFor(Expense::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('request_type', 50)->default('other');
            $table->string('title');
            $table->text('description')->nullable();

            $table->decimal('amount', 15, 2)->default(0);
            $table->string('priority', 30)->default('normal');
            $table->string('status', 30)->default('pending');

            $table->date('expected_date')->nullable();

            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['request_type', 'status']);
            $table->index(['property_id', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_requests');
    }
};
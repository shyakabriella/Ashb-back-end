<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the contract signed date.
     */
    public function up(): void
    {
        if (
            !Schema::hasColumn(
                'properties',
                'contract_signed_date'
            )
        ) {
            Schema::table(
                'properties',
                function (Blueprint $table) {
                    $table
                        ->date('contract_signed_date')
                        ->nullable()
                        ->after('location');
                }
            );
        }
    }

    /**
     * Remove the contract signed date.
     */
    public function down(): void
    {
        if (
            Schema::hasColumn(
                'properties',
                'contract_signed_date'
            )
        ) {
            Schema::table(
                'properties',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'contract_signed_date'
                    );
                }
            );
        }
    }
};

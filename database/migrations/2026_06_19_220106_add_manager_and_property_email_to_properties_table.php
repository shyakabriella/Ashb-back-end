<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addManagerName = !Schema::hasColumn('properties', 'manager_name');
        $addManagerEmail = !Schema::hasColumn('properties', 'manager_email');
        $addPropertyEmail = !Schema::hasColumn('properties', 'property_email');

        if ($addManagerName || $addManagerEmail || $addPropertyEmail) {
            Schema::table('properties', function (Blueprint $table) use (
                $addManagerName,
                $addManagerEmail,
                $addPropertyEmail
            ) {
                if ($addManagerName) {
                    $table->string('manager_name')->nullable()->after('location');
                }

                if ($addManagerEmail) {
                    $table->string('manager_email')->nullable()->after('manager_name');
                }

                if ($addPropertyEmail) {
                    $table->string('property_email')->nullable()->after('manager_email');
                }
            });
        }
    }

    public function down(): void
    {
        $columns = [];

        if (Schema::hasColumn('properties', 'manager_name')) {
            $columns[] = 'manager_name';
        }

        if (Schema::hasColumn('properties', 'manager_email')) {
            $columns[] = 'manager_email';
        }

        if (Schema::hasColumn('properties', 'property_email')) {
            $columns[] = 'property_email';
        }

        if (!empty($columns)) {
            Schema::table('properties', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
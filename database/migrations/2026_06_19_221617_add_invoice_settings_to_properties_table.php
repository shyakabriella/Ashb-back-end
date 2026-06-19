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
        $addPaymentDueDay = !Schema::hasColumn('properties', 'payment_due_day');
        $addAutoInvoiceEnabled = !Schema::hasColumn('properties', 'auto_invoice_enabled');

        if (
            $addManagerName ||
            $addManagerEmail ||
            $addPropertyEmail ||
            $addPaymentDueDay ||
            $addAutoInvoiceEnabled
        ) {
            Schema::table('properties', function (Blueprint $table) use (
                $addManagerName,
                $addManagerEmail,
                $addPropertyEmail,
                $addPaymentDueDay,
                $addAutoInvoiceEnabled
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

                if ($addPaymentDueDay) {
                    $table->unsignedTinyInteger('payment_due_day')->nullable()->after('property_email');
                }

                if ($addAutoInvoiceEnabled) {
                    $table->boolean('auto_invoice_enabled')->default(true)->after('payment_due_day');
                }
            });
        }
    }

    public function down(): void
    {
        $columns = [];

        foreach ([
            'manager_name',
            'manager_email',
            'property_email',
            'payment_due_day',
            'auto_invoice_enabled',
        ] as $column) {
            if (Schema::hasColumn('properties', $column)) {
                $columns[] = $column;
            }
        }

        if (!empty($columns)) {
            Schema::table('properties', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
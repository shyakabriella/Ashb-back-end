<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Invoice;
use App\Models\Property;
use App\Notifications\PropertyInvoiceNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Throwable;

class InvoiceController extends BaseController
{
    /**
     * Deduct VAT from the entered final amount.
     *
     * Example:
     * Total: 5,000
     * VAT: 900
     * Subtotal: 4,100
     */
    private function calculateVat(
        float $totalAmount
    ): array {
        $totalAmount = max(
            round($totalAmount, 2),
            0
        );

        $vatAmount = round(
            $totalAmount * (self::VAT_RATE / 100),
            2
        );

        $subtotal = max(
            round(
                $totalAmount - $vatAmount,
                2
            ),
            0
        );

        return [
            'subtotal' => $subtotal,
            'vat_rate' => self::VAT_RATE,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function parseDate(
        ?string $value,
        Carbon $fallback
    ): Carbon {
        $value = trim(
            (string) ($value ?? '')
        );

        if ($value === '') {
            return $fallback->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return $fallback->copy();
        }
    }

    private function cleanEmail(
        ?string $email
    ): ?string {
        $email = trim(
            (string) ($email ?? '')
        );

        return filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
            ? $email
            : null;
    }
}

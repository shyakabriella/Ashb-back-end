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
     * Rwanda standard VAT rate.
     */
    private const VAT_RATE = 18.0;

    private const CURRENCY = 'RWF';

    public function index(Request $request)
    {
        $query = Invoice::query()
            ->with(
                'property:id,title,manager_name,manager_email,property_email'
            )
            ->latest('id');

        if ($request->filled('property_id')) {
            $query->where(
                'property_id',
                $request->integer('property_id')
            );
        }

        if (
            $request->filled('payment_status')
            && $request->input('payment_status') !== 'all'
        ) {
            $query->where(
                'payment_status',
                $request->input('payment_status')
            );
        }

        if (
            $request->filled('invoice_status')
            && $request->input('invoice_status') !== 'all'
        ) {
            $query->where(
                'invoice_status',
                $request->input('invoice_status')
            );
        }

        $invoices = $query->paginate(
            min(
                max(
                    (int) $request->input('per_page', 20),
                    1
                ),
                100
            )
        );

        return $this->sendResponse(
            $invoices,
            'Invoices retrieved successfully.'
        );
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('property');

        return $this->sendResponse(
            $invoice,
            'Invoice retrieved successfully.'
        );
    }

    /**
     * Create or resend a property invoice.
     *
     * The submitted amount/property price is treated as the
     * subtotal before VAT. VAT is calculated automatically
     * at 18%, and invoice.amount stores the final total.
     */
    public function pushPropertyInvoice(
        Request $request,
        Property $property
    ) {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_id' => 'nullable|string|max:255',
                'amount' => 'nullable|numeric|min:0',
                'invoice_date' => 'nullable|string|max:255',
                'due_date' => 'nullable|string|max:255',
                'force' => 'nullable|boolean',
            ]
        );

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        $recipient = $this->cleanEmail(
            $property->property_email
        );

        $managerCc = $this->cleanEmail(
            $property->manager_email
        );

        if (!$recipient) {
            return $this->sendError(
                'Property email not found.',
                [
                    'property_email' => [
                        'Please add a valid property email '
                        . 'before pushing invoice.',
                    ],
                ],
                422
            );
        }

        $invoiceDate = $this->parseDate(
            $data['invoice_date'] ?? null,
            now()
        );

        $dueDate = $this->parseDate(
            $data['due_date'] ?? null,
            now()->addDays(7)
        );

        $invoiceNumber = trim(
            (string) ($data['invoice_id'] ?? '')
        );

        if ($invoiceNumber === '') {
            $invoiceNumber = Invoice::makeInvoiceNumber(
                $property,
                $dueDate
            );
        }

        /*
         * The provided amount is the subtotal before VAT.
         */
        $subtotal = array_key_exists('amount', $data)
            ? (float) $data['amount']
            : (float) ($property->price ?? 0);

        $pricing = $this->calculateVat($subtotal);

        /*
         * Use firstOrNew so existing metadata such as payment
         * gateway details is not removed when resending.
         */
        $invoice = Invoice::firstOrNew([
            'invoice_number' => $invoiceNumber,
        ]);

        $existingMetadata = is_array($invoice->metadata)
            ? $invoice->metadata
            : [];

        $invoice->forceFill([
            'property_id' => $property->id,
            'property_name' => $property->title,
            'manager_name' => $property->manager_name,
            'property_email' => $recipient,
            'manager_email' => $managerCc,

            /*
             * invoice.amount stores the full payable total.
             * PesaPal will therefore charge subtotal + VAT.
             */
            'amount' => $pricing['total_amount'],

            'currency' => self::CURRENCY,
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'invoice_status' => Invoice::INVOICE_STATUS_ISSUED,
            'payment_status' => Invoice::PAYMENT_STATUS_UNPAID,

            'metadata' => array_merge(
                $existingMetadata,
                [
                    'subtotal' => $pricing['subtotal'],
                    'vat_rate' => $pricing['vat_rate'],
                    'vat_amount' => $pricing['vat_amount'],

                    /*
                     * Keep "vat" for compatibility with the
                     * existing PDF template.
                     */
                    'vat' => $pricing['vat_amount'],

                    'total_amount' => $pricing['total_amount'],
                    'amount_includes_vat' => true,
                    'force_pushed' => (bool) (
                        $data['force'] ?? true
                    ),
                    'source' => 'manual_push',
                ]
            ),
        ]);

        $invoice->save();

        try {
            Notification::route(
                'mail',
                $recipient
            )->notify(
                new PropertyInvoiceNotification(
                    $invoice->fresh('property'),
                    'invoice'
                )
            );

            $invoice->forceFill([
                'sent_at' => now(),
            ])->save();

            $freshInvoice = $invoice->fresh([
                'property:id,title,address,location,manager_name,manager_email,property_email',
            ]);

            return $this->sendResponse(
                [
                    'invoice' =>
                        $this->transformInvoice(
                            $freshInvoice
                        ),
                    'recipient' => $recipient,
                    'cc' => $managerCc,

                    'pricing' => [
                        'currency' => self::CURRENCY,
                        'subtotal' => $pricing['subtotal'],
                        'vat_rate' => $pricing['vat_rate'],
                        'vat_amount' => $pricing['vat_amount'],
                        'total_amount' => $pricing['total_amount'],
                    ],

                    'copy_emails' => [
                        'hotelandsafari@gmail.com',
                        'shyakas83@gmail.com',
                    ],
                ],
                'Invoice pushed successfully to '
                . $recipient
                . '.'
            );
        } catch (Throwable $exception) {
            Log::error(
                'Invoice push failed.',
                [
                    'property_id' => $property->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'subtotal' => $pricing['subtotal'],
                    'vat_rate' => $pricing['vat_rate'],
                    'vat_amount' => $pricing['vat_amount'],
                    'total_amount' => $pricing['total_amount'],
                    'error' => $exception->getMessage(),
                ]
            );

            return $this->sendError(
                'Invoice email could not be sent.',
                [
                    'mail' => [
                        $exception->getMessage(),
                    ],
                ],
                500
            );
        }
    }

    public function markPaid(
        Request $request,
        Invoice $invoice
    ) {
        $invoice->forceFill([
            'payment_status' => Invoice::PAYMENT_STATUS_PAID,
        ])->save();

        return $this->sendResponse(
            [
                'invoice' =>
                    $this->transformInvoice($invoice),
            ],
            'Invoice marked as paid successfully.'
        );
    }

    /**
     * Calculate VAT and the final invoice amount.
     */
    private function calculateVat(float $subtotal): array
    {
        $subtotal = max(
            round($subtotal, 2),
            0
        );

        $vatAmount = round(
            $subtotal * (self::VAT_RATE / 100),
            2
        );

        $totalAmount = round(
            $subtotal + $vatAmount,
            2
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

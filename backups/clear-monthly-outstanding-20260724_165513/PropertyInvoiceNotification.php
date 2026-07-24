<?php

namespace App\Notifications;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

class PropertyInvoiceNotification extends Notification
{
    use Queueable;

    private const COMPANY_COPY_EMAILS = [
        'hotelandsafari@gmail.com',
        'shyakas83@gmail.com',
    ];

    public function __construct(
        public Invoice $invoice,
        public string $mode = 'invoice',
        public ?int $daysBeforeDue = null
    ) {
        $this->invoice->loadMissing('property');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(
        object $notifiable
    ): MailMessage {
        $invoice = $this->invoice->loadMissing(
            'property'
        );

        $property = $invoice->property;

        $propertyName = $invoice->property_name
            ?: optional($property)->title
            ?: 'Property';

        $frontendUrl = rtrim(
            (string) env(
                'APP_FRONTEND_URL',
                'https://www.d.ashbhub.com'
            ),
            '/'
        );

        $storedPaymentUrl = trim(
            (string) (
                $invoice->getAttribute(
                    'payment_url'
                )
                ?: $invoice->getAttribute(
                    'checkout_url'
                )
                ?: ''
            )
        );

        $paymentUrl = $storedPaymentUrl !== ''
            ? $storedPaymentUrl
            : $frontendUrl
                . '/invoices/'
                . $invoice->id
                . '/pay';

        $pdfUrl = Route::has('invoices.pdf')
            ? URL::temporarySignedRoute(
                'invoices.pdf',
                now()->addDays(30),
                [
                    'invoice' => $invoice->id,
                ]
            )
            : '#';

        $paymentStatus = strtolower(
            (string) $invoice->payment_status
        );

        $isPaid = in_array(
            $paymentStatus,
            [
                'paid',
                'completed',
                'success',
                'successful',
            ],
            true
        );

        // Previous unpaid invoices shown in the email and PDF.
        $outstandingStatuses = [
            Invoice::PAYMENT_STATUS_UNPAID,
            Invoice::PAYMENT_STATUS_OVERDUE,
        ];

        $currentMonthStart = $invoice->invoice_date
            ? $invoice->invoice_date
                ->copy()
                ->startOfMonth()
            : null;

        /*
         * Only include invoices from earlier billing months.
         * When a month contains duplicate invoices, keep the
         * latest invoice record for that month.
         */
        $previousUnpaidInvoices = Invoice::query()
            ->where(
                'id',
                '<>',
                $invoice->id
            )
            ->where(
                'invoice_status',
                Invoice::INVOICE_STATUS_ISSUED
            )
            ->whereIn(
                'payment_status',
                $outstandingStatuses
            )
            ->when(
                $currentMonthStart,
                function ($query) use (
                    $currentMonthStart
                ) {
                    $query->whereDate(
                        'invoice_date',
                        '<',
                        $currentMonthStart
                            ->toDateString()
                    );
                }
            )
            ->where(function ($query) use (
                $invoice,
                $propertyName
            ) {
                if ($invoice->property_id) {
                    $query
                        ->where(
                            'property_id',
                            $invoice->property_id
                        )
                        ->orWhere(function (
                            $nameQuery
                        ) use ($propertyName) {
                            $nameQuery
                                ->whereNull('property_id')
                                ->whereRaw(
                                    'LOWER(TRIM(property_name)) = ?',
                                    [
                                        strtolower(
                                            trim($propertyName)
                                        ),
                                    ]
                                );
                        });

                    return;
                }

                $query->whereRaw(
                    'LOWER(TRIM(property_name)) = ?',
                    [
                        strtolower(
                            trim($propertyName)
                        ),
                    ]
                );
            })
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy(
                function (
                    Invoice $previousInvoice
                ): string {
                    return optional(
                        $previousInvoice->invoice_date
                    )->format('Y-m')
                        ?: 'invoice-'
                            . $previousInvoice->id;
                }
            )
            ->map(
                fn ($monthlyInvoices) =>
                    $monthlyInvoices
                        ->sortByDesc('id')
                        ->first()
            )
            ->filter()
            ->sortBy(
                fn (Invoice $previousInvoice) =>
                    optional(
                        $previousInvoice->invoice_date
                    )->format('Y-m-d')
                        ?: ''
            )
            ->values();

        $prepareOutstandingInvoice =
            static function (
                Invoice $previousInvoice
            ): void {
                $previousMetadata = is_array(
                    $previousInvoice->metadata
                )
                    ? $previousInvoice->metadata
                    : [];

                $vatAmount = (float) (
                    $previousMetadata['vat_amount']
                    ?? $previousMetadata['vat']
                    ?? 0
                );

                $amountIncludesVat = filter_var(
                    $previousMetadata[
                        'amount_includes_vat'
                    ] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );

                $vatCalculation = (string) (
                    $previousMetadata[
                        'vat_calculation'
                    ] ?? ''
                );

                /*
                 * Older records added VAT on top even though
                 * the entered amount already included VAT.
                 *
                 * Example:
                 * old subtotal = 800,000
                 * VAT = 144,000
                 * old total = 944,000
                 *
                 * Correct:
                 * subtotal = 656,000
                 * VAT = 144,000
                 * total = 800,000
                 */
                $isLegacyAddedVat =
                    $amountIncludesVat
                    && $vatCalculation !==
                        'deducted_from_invoice_amount'
                    && array_key_exists(
                        'subtotal',
                        $previousMetadata
                    );

                if ($isLegacyAddedVat) {
                    $totalAmount = (float)
                        $previousMetadata['subtotal'];

                    $subtotal = max(
                        $totalAmount - $vatAmount,
                        0
                    );
                } else {
                    $totalAmount = (float) (
                        $previousMetadata[
                            'total_amount'
                        ]
                        ?? $previousInvoice->amount
                        ?? 0
                    );

                    $subtotal = (float) (
                        $previousMetadata['subtotal']
                        ?? max(
                            $totalAmount - $vatAmount,
                            0
                        )
                    );
                }

                $previousInvoice->setAttribute(
                    'display_subtotal',
                    $subtotal
                );

                $previousInvoice->setAttribute(
                    'display_vat_amount',
                    $vatAmount
                );

                $previousInvoice->setAttribute(
                    'display_total_amount',
                    $totalAmount
                );

                $previousInvoice->setAttribute(
                    'display_billing_month',
                    optional(
                        $previousInvoice->invoice_date
                    )->format('F Y') ?: '—'
                );

                $previousInvoice->setAttribute(
                    'display_due_date',
                    optional(
                        $previousInvoice->due_date
                    )->format('d M Y') ?: '—'
                );
            };

        $previousUnpaidInvoices->each(
            $prepareOutstandingInvoice
        );

        $previousOutstandingTotal = (float)
            $previousUnpaidInvoices->sum(
                fn (Invoice $previousInvoice) =>
                    (float) $previousInvoice
                        ->getAttribute(
                            'display_total_amount'
                        )
            );

        $currentMetadata = is_array(
            $invoice->metadata
        )
            ? $invoice->metadata
            : [];

        $currentVatAmount = (float) (
            $currentMetadata['vat_amount']
            ?? $currentMetadata['vat']
            ?? 0
        );

        $currentIncludesVat = filter_var(
            $currentMetadata[
                'amount_includes_vat'
            ] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $currentVatCalculation = (string) (
            $currentMetadata[
                'vat_calculation'
            ] ?? ''
        );

        $currentIsLegacyAddedVat =
            $currentIncludesVat
            && $currentVatCalculation !==
                'deducted_from_invoice_amount'
            && array_key_exists(
                'subtotal',
                $currentMetadata
            );

        if ($currentIsLegacyAddedVat) {
            $resolvedCurrentTotal = (float)
                $currentMetadata['subtotal'];
        } else {
            $resolvedCurrentTotal = (float) (
                $currentMetadata['total_amount']
                ?? $invoice->amount
                ?? 0
            );
        }

        $currentOutstandingAmount = in_array(
            $paymentStatus,
            $outstandingStatuses,
            true
        )
            ? $resolvedCurrentTotal
            : 0.0;

        $grandOutstandingTotal =
            $currentOutstandingAmount
            + $previousOutstandingTotal;

        $daysBeforeDue =
            $this->resolvedDaysBeforeDue();

        $isReminder =
            $this->mode === 'reminder';

        $isOutstandingReminder =
            $isReminder
            && $daysBeforeDue === 0;

        $subject = match (true) {
            $isOutstandingReminder =>
                'Outstanding invoice - '
                    . $propertyName,

            $isReminder && $daysBeforeDue === 1 =>
                'Payment due in 1 day - '
                    . $propertyName,

            $isReminder =>
                'Payment due in '
                    . $daysBeforeDue
                    . ' days - '
                    . $propertyName,

            default =>
                'Property invoice - '
                    . $propertyName,
        };

        $pdf = Pdf::loadView(
            'pdf.property-invoice',
            [
                'invoice' => $invoice,
                'property' => $property,
                'paymentUrl' => $paymentUrl,
                    'previousUnpaidInvoices' =>
                        $previousUnpaidInvoices,
                    'previousOutstandingTotal' =>
                        $previousOutstandingTotal,
                    'currentOutstandingAmount' =>
                        $currentOutstandingAmount,
                    'grandOutstandingTotal' =>
                        $grandOutstandingTotal,

            ]
        )->setPaper(
            'a4',
            'portrait'
        );

        $fileName = 'ASHBHUB-Invoice-'
            . str_pad(
                (string) $invoice->id,
                6,
                '0',
                STR_PAD_LEFT
            )
            . '.pdf';

        $message = (new MailMessage)
            ->subject($subject)
            ->view(
                'emails.property-invoice',
                [
                    'invoice' => $invoice,
                    'property' => $property,
                    'mode' => $this->mode,
                    'daysBeforeDue' =>
                        $this
                            ->resolvedDaysBeforeDue(),
                    'paymentUrl' => $paymentUrl,
                    'previousUnpaidInvoices' =>
                        $previousUnpaidInvoices,
                    'previousOutstandingTotal' =>
                        $previousOutstandingTotal,
                    'currentOutstandingAmount' =>
                        $currentOutstandingAmount,
                    'grandOutstandingTotal' =>
                        $grandOutstandingTotal,

                    'pdfUrl' => $pdfUrl,
                ]
            )
            ->attachData(
                $pdf->output(),
                $fileName,
                [
                    'mime' => 'application/pdf',
                ]
            );

        $managerCc = $invoice->managerCcEmail();

        if ($managerCc) {
            $message->cc($managerCc);
        }

        return $message->bcc(
            self::COMPANY_COPY_EMAILS
        );
    }

    public function toArray(
        object $notifiable
    ): array {
        return [
            'invoice_id' =>
                $this->invoice->id,

            'invoice_number' =>
                $this->invoice->invoice_number,

            'property_id' =>
                $this->invoice->property_id,

            'amount' =>
                (float) $this->invoice->amount,

            'due_date' =>
                optional(
                    $this->invoice->due_date
                )->toDateString(),

            'mode' =>
                $this->mode,

            'days_before_due' =>
                $this->resolvedDaysBeforeDue(),
        ];
    }

    private function resolvedDaysBeforeDue(): int
    {
        if ($this->daysBeforeDue !== null) {
            return max(
                0,
                $this->daysBeforeDue
            );
        }

        if (!$this->invoice->due_date) {
            return 0;
        }

        return max(
            0,
            (int) Carbon::today()->diffInDays(
                $this->invoice->due_date,
                false
            )
        );
    }
}

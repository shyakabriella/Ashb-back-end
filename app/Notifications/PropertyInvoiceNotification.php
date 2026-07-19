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

        $isOverdue = !$isPaid
            && $invoice->due_date
            && $invoice->due_date->isPast();

        $subject = match (true) {
            $isOverdue =>
                'Payment reminder - '
                . $propertyName,

            $this->mode === 'reminder' =>
                'Payment reminder - '
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

<?php

namespace App\Notifications;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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

    public function toMail(object $notifiable): MailMessage
    {
        $invoiceNumber = $this->invoice->invoice_number;
        $propertyName = $this->invoice->property_name
            ?: optional($this->invoice->property)->title
            ?: 'Property';

        $subject = $this->mode === 'reminder'
            ? 'Payment Reminder: Invoice ' . $invoiceNumber . ' for ' . $propertyName
            : 'Invoice ' . $invoiceNumber . ' for ' . $propertyName;

        $message = (new MailMessage)
            ->subject($subject)
            ->view('emails.property-invoice', [
                'invoice' => $this->invoice,
                'property' => $this->invoice->property,
                'mode' => $this->mode,
                'daysBeforeDue' => $this->resolvedDaysBeforeDue(),
            ]);

        $managerCc = $this->invoice->managerCcEmail();

        if ($managerCc) {
            $message->cc($managerCc);
        }

        return $message->bcc(self::COMPANY_COPY_EMAILS);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'property_id' => $this->invoice->property_id,
            'amount' => (float) $this->invoice->amount,
            'due_date' => optional($this->invoice->due_date)->toDateString(),
            'mode' => $this->mode,
            'days_before_due' => $this->resolvedDaysBeforeDue(),
        ];
    }

    private function resolvedDaysBeforeDue(): int
    {
        if ($this->daysBeforeDue !== null) {
            return max(0, $this->daysBeforeDue);
        }

        if (!$this->invoice->due_date) {
            return 0;
        }

        return max(
            0,
            Carbon::today()->diffInDays($this->invoice->due_date, false)
        );
    }
}
<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    public const INVOICE_STATUS_ISSUED = 'issued';
    public const INVOICE_STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PARTIAL = 'partial';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'property_id',
        'invoice_number',
        'property_name',
        'manager_name',
        'property_email',
        'manager_email',
        'amount',
        'currency',
        'invoice_date',
        'due_date',
        'invoice_status',
        'payment_status',
        'sent_at',
        'last_reminder_sent_at',
        'last_reminder_days_before_due',
        'reminders_sent',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'last_reminder_days_before_due' => 'integer',
        'reminders_sent' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'currency' => 'RWF',
        'invoice_status' => self::INVOICE_STATUS_ISSUED,
        'payment_status' => self::PAYMENT_STATUS_UNPAID,
        'reminders_sent' => 0,
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function recipientEmail(): ?string
    {
        $email = $this->property_email ?: optional($this->property)->property_email;

        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function managerCcEmail(): ?string
    {
        $email = $this->manager_email ?: optional($this->property)->manager_email;

        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function isPayable(): bool
    {
        return $this->invoice_status === self::INVOICE_STATUS_ISSUED &&
            in_array($this->payment_status, [
                self::PAYMENT_STATUS_UNPAID,
                self::PAYMENT_STATUS_PARTIAL,
                self::PAYMENT_STATUS_OVERDUE,
            ], true);
    }

    public static function makeInvoiceNumber(Property $property, Carbon $dueDate): string
    {
        return 'INV-' .
            $dueDate->format('Ym') .
            '-PROP-' .
            $property->id;
    }
}
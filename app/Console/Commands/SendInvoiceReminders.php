<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Property;
use App\Notifications\PropertyInvoiceNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders {--date= : Optional test date in YYYY-MM-DD format}';

    protected $description = 'Create monthly property invoices and send reminders from 7 days before due date until due date.';

    public function handle(): int
    {
        $today = $this->resolveToday();
        $lastReminderDate = $today->copy()->addDays(7);

        $this->info('Invoice reminder run date: ' . $today->toDateString());

        $this->createUpcomingInvoices($today, $lastReminderDate);

        $invoices = Invoice::query()
            ->with('property')
            ->where('invoice_status', Invoice::INVOICE_STATUS_ISSUED)
            ->whereIn('payment_status', [
                Invoice::PAYMENT_STATUS_UNPAID,
                Invoice::PAYMENT_STATUS_PARTIAL,
                Invoice::PAYMENT_STATUS_OVERDUE,
            ])
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereDate('due_date', '<=', $lastReminderDate->toDateString())
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('last_reminder_sent_at')
                    ->orWhereDate(
                        'last_reminder_sent_at',
                        '!=',
                        $today->toDateString()
                    );
            })
            ->get();

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            if (!$invoice->isPayable()) {
                $skipped++;
                continue;
            }

            $recipient = $invoice->recipientEmail();

            if (!$recipient) {
                $skipped++;

                Log::warning('Invoice reminder skipped: property email missing.', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'property_id' => $invoice->property_id,
                ]);

                continue;
            }

            try {
                $daysBeforeDue = max(
                    0,
                    $today->diffInDays(
                        Carbon::parse($invoice->due_date)->startOfDay(),
                        false
                    )
                );

                Notification::route('mail', $recipient)
                    ->notify(new PropertyInvoiceNotification(
                        $invoice,
                        'reminder',
                        $daysBeforeDue
                    ));

                $invoice->forceFill([
                    'last_reminder_sent_at' => now(),
                    'last_reminder_days_before_due' => $daysBeforeDue,
                    'reminders_sent' => ((int) $invoice->reminders_sent) + 1,
                ])->save();

                $sent++;
            } catch (Throwable $exception) {
                $failed++;

                Log::error('Invoice reminder failed.', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'property_id' => $invoice->property_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Reminders sent: {$sent}");
        $this->info("Skipped: {$skipped}");
        $this->info("Failed: {$failed}");

        return self::SUCCESS;
    }

    private function createUpcomingInvoices(
        Carbon $today,
        Carbon $lastReminderDate
    ): void {
        $properties = Property::query()
            ->where('auto_invoice_enabled', true)
            ->whereNotNull('payment_due_day')
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->get();

        foreach ($properties as $property) {
            $recipient = $this->cleanEmail($property->property_email);

            if (!$recipient) {
                Log::warning('Automatic invoice skipped: property email missing.', [
                    'property_id' => $property->id,
                    'property_title' => $property->title,
                ]);

                continue;
            }

            $dueDate = $this->dueDateForProperty($property, $today);

            if ($dueDate->lt($today) || $dueDate->gt($lastReminderDate)) {
                continue;
            }

            $exists = Invoice::query()
                ->where('property_id', $property->id)
                ->whereDate('due_date', $dueDate->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            Invoice::create([
                'property_id' => $property->id,
                'invoice_number' => Invoice::makeInvoiceNumber(
                    $property,
                    $dueDate
                ),
                'property_name' => $property->title,
                'manager_name' => $property->manager_name,
                'property_email' => $property->property_email,
                'manager_email' => $property->manager_email,
                'amount' => (float) $property->price,
                'currency' => 'RWF',
                'invoice_date' => $today->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'invoice_status' => Invoice::INVOICE_STATUS_ISSUED,
                'payment_status' => Invoice::PAYMENT_STATUS_UNPAID,
                'metadata' => [
                    'source' => 'automatic_monthly_generation',
                    'payment_due_day' => $property->payment_due_day,
                    'generated_by_command' => 'invoices:send-reminders',
                    'generated_on' => $today->toDateString(),
                ],
            ]);
        }
    }

    private function dueDateForProperty(
        Property $property,
        Carbon $today
    ): Carbon {
        $dueDay = (int) $property->payment_due_day;

        if ($dueDay < 1) {
            $dueDay = 1;
        }

        $daysInMonth = $today->copy()->daysInMonth;

        if ($dueDay > $daysInMonth) {
            $dueDay = $daysInMonth;
        }

        return $today
            ->copy()
            ->day($dueDay)
            ->startOfDay();
    }

    private function resolveToday(): Carbon
    {
        $date = trim((string) ($this->option('date') ?? ''));

        if ($date === '') {
            return Carbon::today()->startOfDay();
        }

        try {
            return Carbon::parse($date)->startOfDay();
        } catch (Throwable) {
            return Carbon::today()->startOfDay();
        }
    }

    private function cleanEmail(?string $email): ?string
    {
        $email = trim((string) ($email ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Notifications\PropertyInvoiceNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendOutstandingInvoiceReminders extends Command
{
    protected $signature =
        'invoices:send-outstanding-reminders
        {--dry-run : Show matching invoices without sending email}
        {--invoice= : Send or test one specific invoice ID}';

    protected $description =
        'Send monthly reminders for unpaid invoices from previous months';

    public function handle(): int
    {
        $now = now('Africa/Kigali');

        $monthKey = $now->format('Y-m');

        $currentMonthStart = $now
            ->copy()
            ->startOfMonth()
            ->toDateString();

        $dryRun = (bool) $this->option('dry-run');

        $invoiceId = (int) (
            $this->option('invoice') ?? 0
        );

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $query = Invoice::query()
            ->with('property')
            ->where(
                'payment_status',
                Invoice::PAYMENT_STATUS_UNPAID
            )
            ->whereDate(
                'invoice_date',
                '<',
                $currentMonthStart
            );

        if ($invoiceId > 0) {
            $query->whereKey($invoiceId);
        }

        $query
            ->orderBy('id')
            ->chunkById(
                100,
                function ($invoices) use (
                    $dryRun,
                    $monthKey,
                    $now,
                    &$sent,
                    &$skipped,
                    &$failed
                ): void {
                    foreach ($invoices as $invoice) {
                        $metadata = is_array(
                            $invoice->metadata
                        )
                            ? $invoice->metadata
                            : [];

                        /*
                         * Do not send the same reminder more
                         * than once during the same month.
                         */
                        if (
                            (
                                $metadata[
                                    'last_outstanding_reminder_month'
                                ] ?? null
                            ) === $monthKey
                        ) {
                            $this->line(
                                "SKIPPED #{$invoice->id}: "
                                . 'reminder already sent '
                                . "for {$monthKey}"
                            );

                            $skipped++;
                            continue;
                        }

                        $recipient = trim(
                            (string) (
                                $invoice->property_email
                                ?: optional(
                                    $invoice->property
                                )->property_email
                                ?: ''
                            )
                        );

                        if (
                            $recipient === ''
                            || !filter_var(
                                $recipient,
                                FILTER_VALIDATE_EMAIL
                            )
                        ) {
                            $this->warn(
                                "SKIPPED #{$invoice->id}: "
                                . 'valid property email missing'
                            );

                            $skipped++;
                            continue;
                        }

                        $propertyName =
                            $invoice->property_name
                            ?: optional(
                                $invoice->property
                            )->title
                            ?: 'Property';

                        if ($dryRun) {
                            $this->line(
                                "DRY RUN #{$invoice->id}: "
                                . "{$propertyName} -> "
                                . $recipient
                            );

                            continue;
                        }

                        try {
                            Notification::route(
                                'mail',
                                $recipient
                            )->notify(
                                new PropertyInvoiceNotification(
                                    $invoice,
                                    'reminder',
                                    0
                                )
                            );

                            $metadata[
                                'last_outstanding_reminder_month'
                            ] = $monthKey;

                            $metadata[
                                'last_outstanding_reminder_sent_at'
                            ] = $now->toIso8601String();

                            $metadata['reminder_count'] =
                                (int) (
                                    $metadata[
                                        'reminder_count'
                                    ] ?? 0
                                ) + 1;

                            $invoice->forceFill([
                                'metadata' => $metadata,
                            ])->save();

                            $this->info(
                                "SENT #{$invoice->id}: "
                                . "{$propertyName} -> "
                                . $recipient
                            );

                            $sent++;
                        } catch (Throwable $exception) {
                            $this->error(
                                "FAILED #{$invoice->id}: "
                                . $exception->getMessage()
                            );

                            Log::error(
                                'Outstanding invoice reminder failed.',
                                [
                                    'invoice_id' =>
                                        $invoice->id,
                                    'property_id' =>
                                        $invoice->property_id,
                                    'recipient' =>
                                        $recipient,
                                    'error' =>
                                        $exception->getMessage(),
                                ]
                            );

                            $failed++;
                        }
                    }
                }
            );

        $this->newLine();

        $this->table(
            ['Result', 'Count'],
            [
                ['Sent', $sent],
                ['Skipped', $skipped],
                ['Failed', $failed],
                ['Dry run', $dryRun ? 'Yes' : 'No'],
            ]
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}

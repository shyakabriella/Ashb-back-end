@php
    $propertyName = $invoice->property_name ?: optional($property)->title ?: 'Property';
    $managerName = $invoice->manager_name ?: optional($property)->manager_name ?: 'Property Manager';
    $address = optional($property)->address ?: '';
    $location = optional($property)->location ?: '';
    $amount = number_format((float) $invoice->amount, 0);
    $currency = $invoice->currency ?: 'RWF';
    $invoiceDate = optional($invoice->invoice_date)->format('M d, Y') ?: '—';
    $dueDate = optional($invoice->due_date)->format('M d, Y') ?: '—';
    $isReminder = $mode === 'reminder';
    $daysBeforeDue = (int) ($daysBeforeDue ?? 0);

    $logoUrl = rtrim(config('app.url'), '/') . '/ashbhub-logo.png';

    /*
     * The controller may pass $paymentUrl directly.
     * Otherwise, the email falls back to a frontend payment page.
     *
     * Configure APP_FRONTEND_URL in .env when the frontend and API
     * are hosted on different domains.
     */
    $frontendUrl = rtrim(
        (string) env('APP_FRONTEND_URL', config('app.url')),
        '/'
    );

    $paymentUrl = $paymentUrl
        ?? $invoice->payment_url
        ?? $invoice->checkout_url
        ?? $frontendUrl . '/invoices/' . $invoice->id . '/pay';

    $title = $isReminder ? 'Payment Reminder' : 'Invoice Notice';

    if ($isReminder && $daysBeforeDue === 0) {
        $reminderLine = 'Payment is due today.';
    } elseif ($isReminder && $daysBeforeDue === 1) {
        $reminderLine = 'Payment is due tomorrow.';
    } elseif ($isReminder) {
        $reminderLine = 'Payment is due in ' . $daysBeforeDue . ' days.';
    } else {
        $reminderLine = 'Please review the invoice details below.';
    }
@endphp

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $propertyName }}</title>
</head>

<body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:760px;margin:0 auto;padding:34px 18px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 8px 28px rgba(15,23,42,0.06);">

            <div style="padding:28px 32px;border-bottom:1px solid #e5e7eb;background:#ffffff;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="vertical-align:middle;">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;padding-right:14px;">
                                        <img
                                            src="{{ $logoUrl }}"
                                            alt="African Safari & Hotel Booking Hub"
                                            width="72"
                                            height="72"
                                            style="display:block;width:72px;height:72px;object-fit:contain;border-radius:14px;border:1px solid #f3f4f6;"
                                        >
                                    </td>

                                    <td style="vertical-align:middle;">
                                        <div style="font-size:14px;font-weight:900;letter-spacing:1.4px;text-transform:uppercase;color:#ea580c;line-height:1.4;">
                                            African Safari & Hotel Booking Hub
                                        </div>

                                        <div style="font-size:12px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#6b7280;margin-top:4px;line-height:1.4;">
                                            Property Management Billing
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>

                        <td style="text-align:right;vertical-align:middle;">
                            <div style="font-size:12px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#6b7280;">
                                Amount Due
                            </div>

                            <div style="font-size:20px;font-weight:900;color:#ea580c;margin-top:6px;white-space:nowrap;">
                                {{ $currency }} {{ $amount }}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="padding:34px 32px;">
                <h1 style="margin:0;font-size:28px;line-height:1.25;color:#111827;">
                    {{ $title }}
                </h1>

                <p style="margin:16px 0 0;font-size:15px;line-height:1.8;color:#374151;">
                    Dear {{ $managerName }},
                </p>

                @if ($isReminder)
                    <p style="margin:14px 0 0;font-size:15px;line-height:1.8;color:#374151;">
                        We hope you are doing well. This is a polite reminder regarding the upcoming payment for
                        <strong>{{ $propertyName }}</strong>.
                    </p>

                    <p style="margin:12px 0 0;font-size:15px;line-height:1.8;color:#374151;">
                        Kindly arrange payment on or before <strong>{{ $dueDate }}</strong> to keep your account up to date.
                    </p>
                @else
                    <p style="margin:14px 0 0;font-size:15px;line-height:1.8;color:#374151;">
                        Please find below the invoice details for <strong>{{ $propertyName }}</strong>.
                        Kindly review and arrange payment by the due date.
                    </p>
                @endif

                <div style="margin-top:26px;background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:18px 20px;">
                    <div style="font-size:12px;font-weight:900;color:#9a3412;text-transform:uppercase;letter-spacing:.9px;">
                        {{ $isReminder ? 'Payment Reminder' : 'Invoice Summary' }}
                    </div>

                    <div style="font-size:16px;font-weight:900;color:#111827;margin-top:8px;">
                        {{ $reminderLine }}
                    </div>
                </div>

                <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:30px;">
                    <tr>
                        <td style="vertical-align:top;width:55%;padding-right:18px;">
                            <div style="font-size:12px;text-transform:uppercase;font-weight:900;color:#6b7280;letter-spacing:1px;">
                                Billed Property
                            </div>

                            <div style="font-size:20px;font-weight:900;color:#111827;margin-top:9px;line-height:1.4;">
                                {{ $propertyName }}
                            </div>

                            @if ($address || $location)
                                <div style="font-size:14px;color:#4b5563;margin-top:8px;line-height:1.7;">
                                    {{ $address }}{{ $location ? ', ' . $location : '' }}
                                </div>
                            @endif
                        </td>

                        <td style="vertical-align:top;width:45%;padding-left:18px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:13px;color:#6b7280;padding-bottom:10px;">
                                        Invoice Date
                                    </td>

                                    <td style="font-size:13px;font-weight:800;color:#111827;text-align:right;padding-bottom:10px;">
                                        {{ $invoiceDate }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="font-size:13px;color:#6b7280;padding-bottom:10px;">
                                        Due Date
                                    </td>

                                    <td style="font-size:13px;font-weight:900;color:#ea580c;text-align:right;padding-bottom:10px;">
                                        {{ $dueDate }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="font-size:13px;color:#6b7280;">
                                        Status
                                    </td>

                                    <td style="font-size:13px;font-weight:900;color:#b45309;text-align:right;">
                                        {{ strtoupper(str_replace('_', ' ', $invoice->payment_status)) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:32px;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            <th align="left" style="padding:15px 16px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:1px;border-bottom:1px solid #e5e7eb;">
                                Description
                            </th>

                            <th align="right" style="padding:15px 16px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:1px;border-bottom:1px solid #e5e7eb;">
                                Amount
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td style="padding:18px 16px;font-size:14px;color:#111827;border-bottom:1px solid #e5e7eb;">
                                <strong>{{ $propertyName }}</strong><br>
                                <span style="font-size:13px;color:#6b7280;">
                                    Monthly property management billing
                                </span>
                            </td>

                            <td align="right" style="padding:18px 16px;font-size:14px;font-weight:900;color:#111827;border-bottom:1px solid #e5e7eb;">
                                {{ $currency }} {{ $amount }}
                            </td>
                        </tr>

                        <tr style="background:#fff7ed;">
                            <td align="right" style="padding:18px 16px;font-size:14px;font-weight:900;color:#111827;">
                                Total Due
                            </td>

                            <td align="right" style="padding:18px 16px;font-size:20px;font-weight:900;color:#ea580c;">
                                {{ $currency }} {{ $amount }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                @if (strtolower((string) $invoice->payment_status) !== 'paid')
                    <table
                        role="presentation"
                        width="100%"
                        cellpadding="0"
                        cellspacing="0"
                        style="margin-top:28px;"
                    >
                        <tr>
                            <td align="center">
                                <a
                                    href="{{ $paymentUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="display:inline-block;background:#ea580c;color:#ffffff;text-decoration:none;font-size:15px;font-weight:900;letter-spacing:.4px;padding:15px 34px;border-radius:12px;box-shadow:0 8px 18px rgba(234,88,12,.22);"
                                >
                                    Pay Now
                                </a>
                            </td>
                        </tr>
                    </table>

                    <p style="margin:14px 0 0;text-align:center;font-size:12px;line-height:1.6;color:#6b7280;">
                        Use the secure payment button above to complete this invoice.
                    </p>
                @else
                    <div style="margin-top:28px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:15px 18px;text-align:center;font-size:14px;font-weight:900;color:#047857;">
                        Payment completed
                    </div>
                @endif

                <p style="margin:26px 0 0;font-size:14px;line-height:1.8;color:#4b5563;">
                    Please contact our billing team if this payment has already been completed or if you need assistance regarding this invoice.
                </p>

                <p style="margin:18px 0 0;font-size:14px;line-height:1.8;color:#4b5563;">
                    Thank you for your continued cooperation.
                </p>

                <p style="margin:26px 0 0;font-size:14px;line-height:1.8;color:#111827;">
                    Kind regards,<br>
                    <strong>African Safari & Hotel Booking Hub Billing Team</strong>
                </p>
            </div>
        </div>

        <p style="text-align:center;font-size:12px;color:#9ca3af;margin-top:18px;line-height:1.6;">
            This is an automated billing email from African Safari & Hotel Booking Hub.
        </p>
    </div>
</body>
</html>
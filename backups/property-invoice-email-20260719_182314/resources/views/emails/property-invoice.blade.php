@php
    $propertyName = $invoice->property_name
        ?: optional($property)->title
        ?: 'Property';

    $managerName = $invoice->manager_name
        ?: optional($property)->manager_name
        ?: 'Property Manager';

    $currency = $invoice->currency ?: 'RWF';
    $amount = number_format((float) $invoice->amount, 0);

    $invoiceDate = optional($invoice->invoice_date)
        ->format('d M Y') ?: '—';

    $dueDate = optional($invoice->due_date)
        ->format('d M Y') ?: '—';

    $paymentStatus = strtolower(
        (string) ($invoice->payment_status ?: 'unpaid')
    );

    $isPaid = in_array(
        $paymentStatus,
        ['paid', 'completed', 'success', 'successful'],
        true
    );

    $isOverdue = !$isPaid
        && optional($invoice->due_date)->isPast();

    $isReminder = ($mode ?? 'invoice') === 'reminder';

    $logoUrl = rtrim(config('app.url'), '/')
        . '/ashbhub-logo.png';

    $frontendUrl = rtrim(
        (string) env(
            'APP_FRONTEND_URL',
            'https://www.d.ashbhub.com'
        ),
        '/'
    );

    $paymentUrl = $paymentUrl
        ?? $invoice->payment_url
        ?? $invoice->checkout_url
        ?? $frontendUrl
            . '/invoices/'
            . $invoice->id
            . '/pay';

    $pdfUrl = $pdfUrl ?? '#';

    $summaryTitle = $isOverdue
        ? 'Summary of your overdue invoice'
        : 'Summary of your invoice';

    $introText = $isOverdue
        ? 'We noticed that this invoice is overdue for payment. Please review the details below and arrange payment as soon as possible.'
        : 'Please review the invoice details below and arrange payment by the due date.';

    $statusColor = $isPaid
        ? '#047857'
        : ($isOverdue ? '#dc2626' : '#d97706');

    $statusLabel = strtoupper(
        str_replace('_', ' ', $paymentStatus)
    );
@endphp

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $summaryTitle }}</title>
</head>

<body style="margin:0;background:#f6f7f9;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:680px;margin:0 auto;padding:28px 16px 34px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;">
            <div style="padding:34px 34px 18px;text-align:center;">
                <img
                    src="{{ $logoUrl }}"
                    alt="African Safari & Hotel Booking Hub"
                    width="112"
                    style="display:block;width:112px;max-width:112px;height:auto;margin:0 auto;"
                >

                <div style="margin-top:15px;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:#F9A800;">
                    African Safari & Hotel Booking Hub
                </div>
            </div>

            <div style="padding:20px 38px 38px;">
                <p style="margin:0;font-size:15px;line-height:1.75;color:#111827;">
                    Hi {{ $managerName }},
                </p>

                <p style="margin:16px 0 0;font-size:15px;line-height:1.75;color:#374151;">
                    Thank you for your continued partnership with African Safari & Hotel Booking Hub.
                </p>

                <p style="margin:12px 0 0;font-size:15px;line-height:1.75;color:#374151;">
                    {{ $introText }}
                </p>

                <h1 style="margin:30px 0 0;font-size:26px;line-height:1.25;color:#111827;">
                    {{ $summaryTitle }}
                </h1>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;border-collapse:collapse;">
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#4b5563;">
                            Property name
                        </td>
                        <td align="right" style="padding:10px 0;border-bottom:1px solid #eeeeee;font-size:14px;font-weight:800;color:#111827;">
                            {{ $propertyName }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#4b5563;">
                            Invoice date
                        </td>
                        <td align="right" style="padding:10px 0;border-bottom:1px solid #eeeeee;font-size:14px;font-weight:800;color:#111827;">
                            {{ $invoiceDate }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#4b5563;">
                            Invoice balance
                        </td>
                        <td align="right" style="padding:10px 0;border-bottom:1px solid #eeeeee;font-size:15px;font-weight:900;color:#ff6246;">
                            {{ $currency }} {{ $amount }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#4b5563;">
                            This invoice due by
                        </td>
                        <td align="right" style="padding:10px 0;border-bottom:1px solid #eeeeee;font-size:14px;font-weight:900;color:#ff6246;">
                            {{ $dueDate }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:10px 0;font-size:14px;color:#4b5563;">
                            Payment status
                        </td>
                        <td align="right" style="padding:10px 0;font-size:14px;font-weight:900;color:{{ $statusColor }};">
                            {{ $statusLabel }}
                        </td>
                    </tr>
                </table>

                @if (!$isPaid)
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:30px;">
                        <tr>
                            <td align="center">
                                <a
                                    href="{{ $paymentUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="display:inline-block;background:#ff6246;color:#ffffff;text-decoration:none;font-size:16px;font-weight:800;padding:15px 28px;border-radius:28px;"
                                >
                                    Pay your invoice now
                                </a>
                            </td>
                        </tr>
                    </table>
                @else
                    <div style="margin-top:28px;background:#ecfdf5;border:1px solid #a7f3d0;padding:14px 18px;text-align:center;font-size:14px;font-weight:900;color:#047857;">
                        Payment completed
                    </div>
                @endif

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;">
                    <tr>
                        <td align="center">
                            <a
                                href="{{ $pdfUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                style="display:inline-block;color:#ea580c;text-decoration:underline;font-size:14px;font-weight:800;"
                            >
                                Download PDF invoice
                            </a>
                        </td>
                    </tr>
                </table>

                <h2 style="margin:34px 0 0;font-size:17px;line-height:1.35;color:#111827;">
                    Other ways to pay
                </h2>

                <p style="margin:12px 0 0;font-size:14px;line-height:1.75;color:#374151;">
                    The secure payment page supports Visa, Mastercard, MTN Mobile Money, Airtel Money, and bank transfer.
                </p>

                <p style="margin:26px 0 0;font-size:14px;line-height:1.75;color:#374151;">
                    Need help? Contact our billing team at
                    <a href="mailto:hotelandsafari@gmail.com" style="color:#ea580c;font-weight:800;">
                        hotelandsafari@gmail.com
                    </a>
                    or call
                    <a href="tel:+250788471880" style="color:#ea580c;font-weight:800;">
                        +250 788 471 880
                    </a>.
                </p>

                <p style="margin:28px 0 0;font-size:14px;line-height:1.75;color:#111827;">
                    Thanks,<br>
                    <strong>Billing & Collections Team</strong><br>
                    African Safari & Hotel Booking Hub
                </p>
            </div>
        </div>

        <div style="padding:20px 10px 0;text-align:center;font-size:12px;line-height:1.6;color:#9ca3af;">
            This is an automated billing email from African Safari & Hotel Booking Hub.
        </div>
    </div>
</body>
</html>

@php
    $propertyName = $invoice->property_name ?: optional($property)->title ?: 'Property';
    $managerName = $invoice->manager_name ?: optional($property)->manager_name ?: 'Property Manager';
    $address = optional($property)->address ?: '';
    $location = optional($property)->location ?: '';
    $metadata = is_array($invoice->metadata)
        ? $invoice->metadata
        : [];

    $currency = $invoice->currency ?: 'RWF';

    $subtotalValue = (float) (
        $metadata['subtotal']
        ?? $invoice->amount
    );

    $vatRate = (float) (
        $metadata['vat_rate']
        ?? 0
    );

    $vatValue = (float) (
        $metadata['vat_amount']
        ?? $metadata['vat']
        ?? 0
    );

    $totalValue = (float) (
        $metadata['total_amount']
        ?? $invoice->amount
    );

    $subtotal = number_format($subtotalValue, 0);
    $vatAmount = number_format($vatValue, 0);
    $totalAmount = number_format($totalValue, 0);

    $invoiceDate = optional($invoice->invoice_date)->format('M d, Y') ?: '—';
    $dueDate = optional($invoice->due_date)->format('M d, Y') ?: '—';
    $isReminder = $mode === 'reminder';
    $daysBeforeDue = (int) ($daysBeforeDue ?? 0);

    $amount = number_format(
        (float) $invoice->amount,
        0
    );

    try {
        $invoiceDateObject = $invoice->invoice_date
            ? \Carbon\Carbon::parse(
                $invoice->invoice_date
            )
            : null;
    } catch (\Throwable $exception) {
        $invoiceDateObject = null;
    }

    try {
        $dueDateObject = $invoice->due_date
            ? \Carbon\Carbon::parse(
                $invoice->due_date
            )
            : null;
    } catch (\Throwable $exception) {
        $dueDateObject = null;
    }

    $invoiceDate = $invoiceDateObject
        ? $invoiceDateObject->format('d M Y')
        : '—';

    $dueDate = $dueDateObject
        ? $dueDateObject->format('d M Y')
        : '—';

    $paymentStatus = strtolower(
        (string) (
            $invoice->payment_status
            ?: 'unpaid'
        )
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
        && $dueDateObject
        && $dueDateObject->isPast();

    $statusLabel = strtoupper(
        str_replace(
            '_',
            ' ',
            $paymentStatus
        )
    );

    $statusColor = $isPaid
        ? '#047857'
        : (
            $isOverdue
                ? '#dc2626'
                : '#d97706'
        );

    $frontendUrl = rtrim(
        (string) env(
            'APP_FRONTEND_URL',
            'https://www.d.ashbhub.com'
        ),
        '/'
    );

    $paymentUrl = $paymentUrl
        ?? $invoice->getAttribute('payment_url')
        ?? $invoice->getAttribute('checkout_url')
        ?? $frontendUrl
            . '/invoices/'
            . $invoice->id
            . '/pay';

    $logoUrl = (string) env(
        'INVOICE_LOGO_URL',
        rtrim(
            (string) config('app.url'),
            '/'
        ) . '/ashbhub-logo.png'
    );

    $summaryTitle = $isOverdue
        ? 'Summary of your outstanding invoice'
        : 'Summary of your invoice';

    $messageText = $isOverdue
        ? 'We hope you are doing well. According to our records, the invoice below remains outstanding. We kindly request that you review the details and arrange payment at your earliest convenience. If payment has already been completed, please disregard this reminder and send us the payment confirmation.'
        : 'Please find below the details of your property management invoice. We kindly ask you to review the information and arrange payment by the due date. A complete PDF copy of the invoice is attached to this email for your records.';
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>
        {{ $summaryTitle }}
    </title>
</head>

<body
    style="margin:0;background:#f5f6f8;font-family:Arial,Helvetica,sans-serif;color:#111827;"
>
    <div
        style="max-width:680px;margin:0 auto;padding:28px 16px 38px;"
    >
        <div
            style="background:#ffffff;border:1px solid #e5e7eb;"
        >
            <!-- Logo -->
            <div
                style="padding:34px 34px 18px;text-align:center;"
            >
                <img
                    src="{{ $logoUrl }}"
                    alt="African Safari and Hotel Booking Hub"
                    width="125"
                    style="display:block;width:125px;max-width:125px;height:auto;margin:0 auto;border:0;"
                >

            <div style="padding:28px 32px;border-bottom:1px solid #e5e7eb;background:#ffffff;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="vertical-align:middle;">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;padding-right:14px;">
                                        <img
                                            src="{{ $logoUrl }}"
                                            alt="Company logo"
                                            width="72"
                                            height="72"
                                            style="display:block;width:72px;height:72px;object-fit:contain;border-radius:14px;border:1px solid #f3f4f6;"
                                        >
                                    </td>

                                    <td style="vertical-align:middle;">
                                        <div style="font-size:12px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#6b7280;margin-top:4px;line-height:1.4;">
                                            Property Management Billing
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>

                        <td style="text-align:right;vertical-align:middle;">
                            <div style="font-size:13px;font-weight:700;color:#6b7280;">
                                Invoice No.
                            </div>

                            <div style="font-size:17px;font-weight:900;color:#111827;margin-top:5px;">
                                {{ $invoice->invoice_number }}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div
                style="padding:20px 38px 38px;"
            >
                <p
                    style="margin:0;font-size:15px;line-height:1.75;color:#111827;"
                >
                    Dear {{ $managerName }},
                </p>

                <p
                    style="margin:16px 0 0;font-size:15px;line-height:1.75;color:#374151;"
                >
                    Thank you for your continued partnership
                    with African Safari &amp; Hotel Booking Hub.
                </p>

                <p
                    style="margin:12px 0 0;font-size:15px;line-height:1.75;color:#374151;"
                >
                    {{ $messageText }}
                </p>

                <!-- Invoice summary -->
                <h1
                    style="margin:32px 0 0;font-size:26px;line-height:1.25;color:#111827;"
                >
                    {{ $summaryTitle }}
                </h1>

                <table
                    role="presentation"
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    style="margin-top:24px;border-collapse:collapse;"
                >
                    <tr>
                        <td
                            style="padding:11px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#4b5563;"
                        >
                            Property name
                        </td>

                        <td
                            align="right"
                            style="padding:11px 0;border-bottom:1px solid #eeeeee;font-size:14px;font-weight:800;color:#111827;"
                        >
                            {{ $propertyName }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding:11px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#4b5563;"
                        >
                            Invoice date
                        </td>

                        <td
                            align="right"
                            style="padding:11px 0;border-bottom:1px solid #eeeeee;font-size:14px;font-weight:800;color:#111827;"
                        >
                            {{ $invoiceDate }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding:11px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#4b5563;"
                        >
                            Invoice balance
                        </td>

                        <td
                            align="right"
                            style="padding:11px 0;border-bottom:1px solid #eeeeee;font-size:15px;font-weight:900;color:#F05A37;"
                        >
                            {{ $currency }} {{ $amount }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding:11px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#4b5563;"
                        >
                            Payment due date
                        </td>

                        <td
                            align="right"
                            style="padding:11px 0;border-bottom:1px solid #eeeeee;font-size:14px;font-weight:900;color:#F05A37;"
                        >
                            {{ $dueDate }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding:11px 0;font-size:14px;color:#4b5563;"
                        >
                            Payment status
                        </td>

                        <td
                            align="right"
                            style="padding:11px 0;font-size:14px;font-weight:900;color:{{ $statusColor }};"
                        >
                            {{ $statusLabel }}
                        </td>
                    </tr>
                </table>

                <!-- PDF attachment notice -->
                <div
                    style="margin-top:25px;padding:14px 16px;background:#f8fafc;border-left:4px solid #F9A800;font-size:13px;line-height:1.65;color:#475569;"
                >
                    A PDF copy of this invoice is attached
                    at the bottom of this email. You may
                    open, download, print, or save it to
                    Google Drive.
                </div>

                <!-- How to pay -->
                <h2
                    style="margin:34px 0 0;font-size:24px;line-height:1.3;color:#111827;"
                >
                    How to pay
                </h2>

                <!-- Bank transfer -->
                <div
                    style="margin-top:22px;font-size:15px;font-weight:800;color:#111827;"
                >
                    1. Bank transfer
                    <span
                        style="font-weight:400;color:#d97706;"
                    >
                        — recommended
                    </span>
                </div>

                            <td align="right" style="padding:18px 16px;font-size:14px;font-weight:900;color:#111827;border-bottom:1px solid #e5e7eb;">
                                {{ $currency }} {{ $subtotal }}
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:16px;font-size:14px;color:#111827;border-bottom:1px solid #e5e7eb;">
                                VAT ({{ number_format($vatRate, 0) }}%)
                            </td>

                            <td align="right" style="padding:16px;font-size:14px;font-weight:900;color:#111827;border-bottom:1px solid #e5e7eb;">
                                {{ $currency }} {{ $vatAmount }}
                            </td>
                        </tr>


                        <tr style="background:#fff7ed;">
                            <td align="right" style="padding:18px 16px;font-size:14px;font-weight:900;color:#111827;">
                                Total Due
                            </td>

                            <td align="right" style="padding:18px 16px;font-size:20px;font-weight:900;color:#ea580c;">
                                {{ $currency }} {{ $totalAmount }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                
                <div
                    data-payment-instructions="true"
                    style="margin-top:26px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px;"
                >
                    <div style="font-size:12px;font-weight:900;color:#6b7280;text-transform:uppercase;letter-spacing:1px;">
                        How to pay
                    </div>

                    <div style="margin-top:10px;font-size:15px;font-weight:900;color:#111827;">
                        Pay securely by Bank Card
                    </div>

                    <div style="margin-top:7px;font-size:14px;line-height:1.7;color:#4b5563;">
                        Use the secure payment button to continue to the protected payment page.
                    </div>

                    <div style="margin-top:10px;font-size:14px;color:#111827;">
                        SWIFT/BIC code:
                        <strong>IMRWRWRWXXX</strong>
                    </div>
                </div>

                <p style="margin:26px 0 0;font-size:14px;line-height:1.8;color:#4b5563;">
                    Please contact our billing team if this payment has already been completed or if you need assistance regarding this invoice.
                </p>

                <!-- Bank card -->
                @if (!$isPaid)
                    <div
                        style="margin-top:28px;font-size:15px;font-weight:800;color:#111827;"
                    >
                        2. Instant bank card payment
                    </div>

                    <p
                        style="margin:10px 0 0;font-size:14px;line-height:1.75;color:#374151;"
                    >
                        You may settle the invoice immediately
                        using a Visa or Mastercard through our
                        secure payment page.
                    </p>

                    <table
                        role="presentation"
                        width="100%"
                        cellpadding="0"
                        cellspacing="0"
                        style="margin-top:17px;"
                    >
                        <tr>
                            <td align="center">
                                <a
                                    href="{{ $paymentUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="display:inline-block;background:#F9A800;color:#ffffff;text-decoration:none;font-size:15px;font-weight:900;padding:15px 30px;border-radius:28px;"
                                >
                                    Pay with Bank Card
                                </a>
                            </td>
                        </tr>
                    </table>
                @else
                    <div
                        style="margin-top:28px;padding:14px 18px;background:#ecfdf5;border:1px solid #a7f3d0;text-align:center;font-size:14px;font-weight:900;color:#047857;"
                    >
                        Payment completed
                    </div>
                @endif

                <!-- Confirmation -->
                <div
                    style="margin-top:30px;font-size:15px;font-weight:800;color:#111827;"
                >
                    3. Confirm your payment
                </div>

                <p
                    style="margin:10px 0 0;font-size:14px;line-height:1.75;color:#374151;"
                >
                    After making a bank transfer, please
                    send the payment receipt or transaction
                    confirmation to
                    <a
                        href="mailto:hotelandsafari@gmail.com"
                        style="color:#ea580c;font-weight:800;"
                    >
                        hotelandsafari@gmail.com
                    </a>.
                    This helps us update your invoice status
                    without delay.
                </p>

                <!-- Help -->
                <p
                    style="margin:30px 0 0;font-size:14px;line-height:1.75;color:#374151;"
                >
                    For any questions concerning this
                    invoice or payment process, contact
                    our billing team by email or call
                    <a
                        href="tel:+250788471880"
                        style="color:#ea580c;font-weight:800;"
                    >
                        +250 788 471 880
                    </a>.
                </p>

                <p
                    style="margin:28px 0 0;font-size:14px;line-height:1.75;color:#111827;"
                >
                    Kind regards,<br>
                    <strong>Billing Team</strong>
                </p>
            </div>
        </div>

        <p style="text-align:center;font-size:12px;color:#9ca3af;margin-top:18px;line-height:1.6;">
            This is an automated billing email.
        </p>
    </div>
</body>
</html>

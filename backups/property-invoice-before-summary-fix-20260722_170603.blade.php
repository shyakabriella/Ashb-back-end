@php
    $isReminder =
        ($mode ?? 'invoice') === 'reminder';

    $daysBeforeDue = max(
        0,
        (int) ($daysBeforeDue ?? 0)
    );

    $isUpcomingReminder =
        $isReminder
        && $daysBeforeDue > 0;

    $isOutstandingReminder =
        $isReminder
        && $daysBeforeDue === 0;

    $remainingDaysText =
        $daysBeforeDue === 1
            ? '1 day'
            : $daysBeforeDue . ' days';

    $propertyName = $invoice->property_name
        ?: optional($property)->title
        ?: 'Property';

    $managerName = $invoice->manager_name
        ?: optional($property)->manager_name
        ?: 'Property Manager';

    $metadata = is_array($invoice->metadata)
        ? $invoice->metadata
        : [];

    /*
     * Email displays the subtotal before VAT.
     * VAT remains visible only in the attached PDF.
     */
    $subtotalValue = (float) (
        $metadata['subtotal']
        ?? $invoice->amount
        ?? 0
    );

    $amount = number_format(
        $subtotalValue,
        0
    );

    $currency = $invoice->currency ?: 'RWF';

    try {
        $invoiceDate = $invoice->invoice_date
            ? \Carbon\Carbon::parse(
                $invoice->invoice_date
            )->format('d M Y')
            : '—';
    } catch (\Throwable $exception) {
        $invoiceDate = '—';
    }

    try {
        $dueDate = $invoice->due_date
            ? \Carbon\Carbon::parse(
                $invoice->due_date
            )->format('d M Y')
            : '—';
    } catch (\Throwable $exception) {
        $dueDate = '—';
    }

    $paymentStatus = strtoupper(
        str_replace(
            '_',
            ' ',
            (string) $invoice->payment_status
        )
    );

    $logoUrl = rtrim(
        config('app.url'),
        '/'
    ) . '/ashbhub-logo.png';

    $paymentPageUrl = $paymentUrl
        ?? (
            'https://www.d.ashbhub.com/invoices/'
            . $invoice->id
            . '/pay'
        );
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Outstanding Invoice
    </title>
</head>

<body
    style="
        margin:0;
        padding:0;
        background:#f4f5f7;
        font-family:Arial,Helvetica,sans-serif;
        color:#0f172a;
    "
>
    <table
        role="presentation"
        width="100%"
        cellpadding="0"
        cellspacing="0"
        border="0"
        style="background:#f4f5f7;"
    >
        <tr>
            <td
                align="center"
                style="padding:0;"
            >
                <table
                    role="presentation"
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    style="
                        max-width:850px;
                        background:#ffffff;
                        border:1px solid #e5e7eb;
                    "
                >
                    <tr>
                        <td
                            style="
                                padding:42px 42px 36px;
                            "
                        >
                            <!-- Header -->
                            <div
                                style="
                                    text-align:center;
                                "
                            >
                                <img
                                    src="{{ $logoUrl }}"
                                    alt="African Safari & Hotel Booking Hub"
                                    width="125"
                                    style="
                                        display:block;
                                        width:125px;
                                        max-width:125px;
                                        height:auto;
                                        margin:0 auto;
                                        border:0;
                                    "
                                >
                            </div>

                            <!-- Greeting -->
                            <p
                                style="
                                    margin:56px 0 0;
                                    font-size:18px;
                                    line-height:1.7;
                                    color:#16213a;
                                "
                            >
                                Dear {{ $managerName }},
                            </p>

                            <p
                                style="
                                    margin:22px 0 0;
                                    font-size:18px;
                                    line-height:1.7;
                                    color:#16213a;
                                "
                            >
                                Thank you for your continued partnership with
                                African Safari &amp; Hotel Booking Hub.
                            </p>

                            @if ($isReminder)
        @if ($isUpcomingReminder)
        <p
                                style="
                                    margin:20px 0 0;
                                    font-size:18px;
                                    line-height:1.8;
                                    color:#16213a;
                                "
                            >
            We hope you are doing well. This is a friendly
            reminder that the payment date for the invoice
            below is approaching.

            You have
            <strong>{{ $remainingDaysText }}</strong>
            remaining before this invoice becomes overdue.

            Please review the invoice details and arrange
            payment on or before the due date. If payment
            has already been completed, please disregard
            this reminder and send us the payment
            confirmation.
        </p>
    @elseif ($isOutstandingReminder)
        <p
                                style="
                                    margin:20px 0 0;
                                    font-size:18px;
                                    line-height:1.8;
                                    color:#16213a;
                                "
                            >
            We hope you are doing well. According to our
            records, the invoice below remains outstanding.

            We kindly request that you review the details
            and arrange payment at your earliest convenience.
            If payment has already been completed, please
            disregard this reminder and send us the payment
            confirmation.
        </p>
    @else
        <p
                                style="
                                    margin:20px 0 0;
                                    font-size:18px;
                                    line-height:1.8;
                                    color:#16213a;
                                "
                            >
            We hope you are doing well. Please find attached
            your monthly billing invoice. Kindly review the
            invoice details and arrange payment by the due
            date shown below.
        </p>
    @endif
    @else
        <p
                                style="
                                    margin:20px 0 0;
                                    font-size:18px;
                                    line-height:1.8;
                                    color:#16213a;
                                "
                            >
            Please find attached your monthly billing invoice.
            Kindly review the invoice details and arrange
            payment by the due date shown below. Please
            contact our billing team when you need
            clarification or a payment arrangement.
        </p>
    @endif

                            <!-- Invoice summary heading -->
                            <h1
                                style="
                                    margin:38px 0 26px;
                                    font-size:31px;
                                    line-height:1.25;
                                    color:#0b1325;
                                    font-weight:800;
                                "
                            >
                                {{ $isReminder
            ? '{{ $isOutstandingReminder
            ? 'Summary of your outstanding invoice'
            : 'Summary of your invoice'
        }}'
            : 'Summary of your monthly billing invoice'
        }}
                            </h1>

                            <!-- Invoice summary table -->
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="
                                    width:100%;
                                    border-collapse:collapse;
                                "
                            >
                                <tr>
                                    <td
                                        style="
                                            padding:15px 0;
                                            border-bottom:1px solid #e5e7eb;
                                            color:#40506c;
                                            font-size:17px;
                                        "
                                    >
                                        Property name
                                    </td>

                                    <td
                                        align="right"
                                        style="
                                            padding:15px 0;
                                            border-bottom:1px solid #e5e7eb;
                                            color:#071126;
                                            font-size:17px;
                                            font-weight:800;
                                        "
                                    >
                                        {{ $propertyName }}
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding:15px 0;
                                            border-bottom:1px solid #e5e7eb;
                                            color:#40506c;
                                            font-size:17px;
                                        "
                                    >
                                        Invoice date
                                    </td>

                                    <td
                                        align="right"
                                        style="
                                            padding:15px 0;
                                            border-bottom:1px solid #e5e7eb;
                                            color:#071126;
                                            font-size:17px;
                                            font-weight:800;
                                        "
                                    >
                                        {{ $invoiceDate }}
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding:15px 0;
                                            border-bottom:1px solid #e5e7eb;
                                            color:#40506c;
                                            font-size:17px;
                                        "
                                    >
                                        Invoice balance
                                    </td>

                                    <td
                                        align="right"
                                        style="
                                            padding:15px 0;
                                            border-bottom:1px solid #e5e7eb;
                                            color:#ff4b32;
                                            font-size:18px;
                                            font-weight:800;
                                        "
                                    >
                                        {{ $currency }} {{ $amount }}
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding:15px 0;
                                            border-bottom:1px solid #e5e7eb;
                                            color:#40506c;
                                            font-size:17px;
                                        "
                                    >
                                        Payment due date
                                    </td>

                                    <td
                                        align="right"
                                        style="
                                            padding:15px 0;
                                            border-bottom:1px solid #e5e7eb;
                                            color:#ff4b32;
                                            font-size:17px;
                                            font-weight:800;
                                        "
                                    >
                                        {{ $dueDate }}
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding:15px 0;
                                            color:#40506c;
                                            font-size:17px;
                                        "
                                    >
                                        Payment status
                                    </td>

                                    <td
                                        align="right"
                                        style="
                                            padding:15px 0;
                                            color:#e11d2e;
                                            font-size:17px;
                                            font-weight:800;
                                        "
                                    >
                                        {{ $paymentStatus }}
                                    </td>
                                </tr>
                            </table>

                            <!-- PDF notice -->
                            <div
                                style="
                                    margin-top:30px;
                                    padding:18px 20px;
                                    background:#f5f7fa;
                                    border-left:4px solid #f5a900;
                                    color:#40506c;
                                    font-size:16px;
                                    line-height:1.7;
                                "
                            >
                                A PDF copy of this invoice is attached at the
                                bottom of this email. You may open, download,
                                print, or save it to Google Drive.
                            </div>

                            <!-- How to pay -->
                            <h2
                                style="
                                    margin:44px 0 0;
                                    font-size:29px;
                                    line-height:1.3;
                                    color:#071126;
                                    font-weight:800;
                                "
                            >
                                How to pay
                            </h2>

                            <!-- Bank transfer -->
                            <h3
                                style="
                                    margin:30px 0 0;
                                    font-size:18px;
                                    line-height:1.5;
                                    color:#071126;
                                    font-weight:800;
                                "
                            >
                                1. Bank transfer
                                <span
                                    style="
                                        color:#f97316;
                                        font-weight:400;
                                    "
                                >
                                    — recommended
                                </span>
                            </h3>

                            <p
                                style="
                                    margin:14px 0 0;
                                    font-size:17px;
                                    line-height:1.7;
                                    color:#16213a;
                                "
                            >
                                Transfer the full invoice amount using the bank
                                account details below.
                            </p>

                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="
                                    width:100%;
                                    margin-top:18px;
                                    border-collapse:collapse;
                                    background:#fffaf4;
                                    border:1px solid #fdba74;
                                "
                            >
                                <tr>
                                    <td
                                        width="40%"
                                        style="
                                            padding:14px 18px;
                                            border-bottom:1px solid #fdba74;
                                            color:#64748b;
                                            font-size:16px;
                                        "
                                    >
                                        Bank name
                                    </td>

                                    <td
                                        width="60%"
                                        style="
                                            padding:14px 18px;
                                            border-bottom:1px solid #fdba74;
                                            color:#071126;
                                            font-size:16px;
                                            font-weight:800;
                                        "
                                    >
                                        I&amp;M BANK Rwanda
                                    </td>
                                </tr>
<tr>
                                    <td
                                        width="40%"
                                        style="
                                            padding:14px 18px;
                                            border-bottom:1px solid #fdba74;
                                            color:#64748b;
                                            font-size:16px;
                                        "
                                    >
                                        SWIFT/BIC code
                                    </td>

                                    <td
                                        width="60%"
                                        style="
                                            padding:14px 18px;
                                            border-bottom:1px solid #fdba74;
                                            color:#071126;
                                            font-size:16px;
                                            font-weight:800;
                                        "
                                    >
                                        IMRWRWRWXXX
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding:14px 18px;
                                            border-bottom:1px solid #fdba74;
                                            color:#64748b;
                                            font-size:16px;
                                        "
                                    >
                                        Account name
                                    </td>

                                    <td
                                        style="
                                            padding:14px 18px;
                                            border-bottom:1px solid #fdba74;
                                            color:#071126;
                                            font-size:16px;
                                            font-weight:800;
                                        "
                                    >
                                        African Safari and Hotel Booking Hub Ltd
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding:14px 18px;
                                            border-bottom:1px solid #fdba74;
                                            color:#64748b;
                                            font-size:16px;
                                        "
                                    >
                                        Account number
                                    </td>

                                    <td
                                        style="
                                            padding:14px 18px;
                                            border-bottom:1px solid #fdba74;
                                            color:#071126;
                                            font-size:16px;
                                            font-weight:800;
                                        "
                                    >
                                        20149677001
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            padding:14px 18px;
                                            color:#64748b;
                                            font-size:16px;
                                        "
                                    >
                                        TIN
                                    </td>

                                    <td
                                        style="
                                            padding:14px 18px;
                                            color:#071126;
                                            font-size:16px;
                                            font-weight:800;
                                        "
                                    >
                                        147893300
                                    </td>
                                </tr>
                            </table>

                            <p
                                style="
                                    margin:16px 0 0;
                                    font-size:16px;
                                    line-height:1.7;
                                    color:#64748b;
                                "
                            >
                                Please use the property name as the transfer
                                reference so our billing team can identify your
                                payment quickly.
                            </p>

                            <!-- Card payment -->
                            <h3
                                style="
                                    margin:36px 0 0;
                                    font-size:18px;
                                    line-height:1.5;
                                    color:#071126;
                                    font-weight:800;
                                "
                            >
                                2. Instant bank card payment
                            </h3>

                            <p
                                style="
                                    margin:14px 0 0;
                                    font-size:17px;
                                    line-height:1.7;
                                    color:#16213a;
                                "
                            >
                                You may settle the invoice immediately using a
                                Visa or Mastercard through our secure payment page.
                            </p>

                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="margin-top:20px;"
                            >
                                <tr>
                                    <td align="center">
                                        <a
                                            href="{{ $paymentPageUrl }}"
                                            style="
                                                display:inline-block;
                                                padding:18px 38px;
                                                background:#f9a900;
                                                border-radius:40px;
                                                color:#ffffff;
                                                font-size:18px;
                                                line-height:1;
                                                font-weight:800;
                                                text-decoration:none;
                                            "
                                        >
                                            Pay with Bank Card
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Confirmation -->
                            <h3
                                style="
                                    margin:38px 0 0;
                                    font-size:18px;
                                    line-height:1.5;
                                    color:#071126;
                                    font-weight:800;
                                "
                            >
                                3. Confirm your payment
                            </h3>

                            <p
                                style="
                                    margin:14px 0 0;
                                    font-size:17px;
                                    line-height:1.7;
                                    color:#16213a;
                                "
                            >
                                After making a bank transfer, please send the
                                payment receipt or transaction confirmation to

                                <a
                                    href="mailto:hotelandsafari@gmail.com"
                                    style="
                                        color:#f4511e;
                                        font-weight:700;
                                        text-decoration:underline;
                                    "
                                >
                                    hotelandsafari@gmail.com
                                </a>.

                                This helps us update your invoice status without
                                delay.
                            </p>

                            <p
                                style="
                                    margin:28px 0 0;
                                    font-size:17px;
                                    line-height:1.7;
                                    color:#16213a;
                                "
                            >
                                For any questions concerning this invoice or
                                payment process, contact our billing team by
                                email or call

                                <a
                                    href="tel:+250788471880"
                                    style="
                                        color:#f4511e;
                                        font-weight:700;
                                        text-decoration:underline;
                                    "
                                >
                                    +250 788 471 880
                                </a>.
                            </p>

                            <!-- Signature -->
                            <p
                                style="
                                    margin:36px 0 0;
                                    font-size:17px;
                                    line-height:1.6;
                                    color:#071126;
                                "
                            >
                                Kind regards,<br>

                                <strong>
                                    Billing &amp; Collections Team
                                </strong><br>

                                African Safari &amp; Hotel Booking Hub
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

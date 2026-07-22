@php
    $propertyName = $invoice->property_name
        ?: optional($property)->title
        ?: 'Property';

    $managerName = $invoice->manager_name
        ?: optional($property)->manager_name
        ?: 'Property Manager';

    $address = optional($property)->address ?: '';
    $location = optional($property)->location ?: '';

    $propertyEmail = $invoice->property_email
        ?: optional($property)->property_email
        ?: '';

    $currency = $invoice->currency ?: 'RWF';

    $metadata = is_array($invoice->metadata)
        ? $invoice->metadata
        : [];

    /*
     * invoice.amount contains the final payable total.
     * The VAT breakdown is stored in metadata.
     */
    $grossAmount = (float) $invoice->amount;

    $amountValue = (float) (
        $metadata['subtotal']
        ?? $grossAmount
    );

    $vatRate = (float) (
        $metadata['vat_rate']
        ?? 0
    );

    $vat = (float) (
        $metadata['vat_amount']
        ?? $metadata['vat']
        ?? 0
    );

    $balanceCarriedForward = (float) (
        $metadata['balance_carried_forward']
        ?? 0
    );

    $adjustments = (float) (
        $metadata['adjustments']
        ?? 0
    );

    $credits = (float) (
        $metadata['credits']
        ?? 0
    );

    $invoiceTotal = (float) (
        $metadata['total_amount']
        ?? max(
            $amountValue
            + $vat
            + $adjustments
            - $credits,
            0
        )
    );

    $payments = strtolower(
        (string) $invoice->payment_status
    ) === 'paid'
        ? $invoiceTotal
        : (float) (
            $metadata['payments']
            ?? 0
        );

    $totalBalance = max(
        $balanceCarriedForward
        + $invoiceTotal
        - $payments,
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

    $billingStart = $invoiceDateObject
        ? $invoiceDateObject
            ->copy()
            ->startOfMonth()
            ->format('d/m/Y')
        : '—';

    $billingEnd = $invoiceDateObject
        ? $invoiceDateObject
            ->copy()
            ->endOfMonth()
            ->format('d/m/Y')
        : '—';

    $frontendUrl = rtrim(
        (string) env(
            'APP_FRONTEND_URL',
            'https://www.d.ashbhub.com'
        ),
        '/'
    );

    $paymentUrl = $paymentUrl
        ?? $frontendUrl
            . '/invoices/'
            . $invoice->id
            . '/pay';

    $invoiceReference = 'ASH-'
        . str_pad(
            (string) $invoice->id,
            6,
            '0',
            STR_PAD_LEFT
        );

    $accountReference = 'PROP-'
        . str_pad(
            (string) $invoice->property_id,
            4,
            '0',
            STR_PAD_LEFT
        );

    $logoPath = public_path(
        'ashbhub-logo.png'
    );
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>
        ASHBHUB Invoice {{ $invoiceReference }}
    </title>

    <style>
        @page {
            margin: 28px 34px 34px;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.45;
            color: #111827;
        }

        table {
            border-collapse: collapse;
        }

        .right {
            text-align: right;
        }

        .muted {
            color: #6b7280;
        }

        .small {
            font-size: 8.5px;
        }

        .invoice-title {
            margin-top: 12px;
            font-size: 24px;
            font-weight: 800;
        }

        .section-title {
            font-size: 12px;
            font-weight: 800;
        }

        .summary-table {
            width: 100%;
        }

        .summary-table td {
            padding: 7px 9px;
        }

        .summary-soft {
            background: #fff7ed;
        }

        .summary-strong {
            background: #F9A800;
            color: #ffffff;
            font-weight: 800;
        }

        .summary-due {
            background: #ffedd5;
            color: #9a3412;
            font-weight: 800;
        }

        .box-title {
            padding: 7px 9px;
            background: #fff7ed;
            border-bottom: 1px solid #111827;
            font-weight: 800;
        }

        .payment-box {
            padding: 11px;
            background: #fffaf5;
            border: 1px solid #111827;
        }

        .detail-table {
            width: 100%;
            margin-top: 10px;
        }

        .detail-table th {
            padding: 8px;
            text-align: left;
            background: #fff7ed;
            border-bottom: 1px solid #111827;
        }

        .detail-table td {
            padding: 8px;
            border-bottom: 1px solid #d1d5db;
        }

        .page-break {
            page-break-before: always;
        }

        .footer {
            position: fixed;
            right: 0;
            bottom: -18px;
            left: 0;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
        }

        a {
            color: #ea580c;
        }
    </style>
</head>

<body>


    <table width="100%">
        <tr>
            <td
                width="52%"
                valign="top"
            >
                @if (is_file($logoPath))
                    <img
                        src="{{ $logoPath }}"
                        alt="ASHBHUB"
                        style="width:105px;height:auto;"
                    >
                @else
                    <div
                        style="font-size:20px;font-weight:900;color:#F9A800;"
                    >
                        ASHBHUB
                    </div>
                @endif

                <div class="invoice-title">
                    Tax Invoice
                </div>
            </td>

            <td
                width="48%"
                valign="top"
                class="right"
            >


                <div class="small muted">
                    Kigali, Rwanda
                </div>

                <div class="small muted">
                    Phone: +250 788 471 880
                </div>

                <div class="small muted">
                    Email: hotelandsafari@gmail.com
                </div>

                <div class="small muted">
                    Website: www.ashbhub.com
                </div>

                <div class="small muted">
                    TIN: 147893300
                </div>

                <div
                    style="margin-top:15px;font-weight:800;"
                >
                    Need help?
                </div>

                <div class="small">
                    Contact our billing team
                </div>
            </td>
        </tr>
    </table>

    <table
        width="100%"
        style="margin-top:28px;"
    >
        <tr>
            <td
                width="47%"
                valign="top"
            >
                <div
                    style="font-size:14px;font-weight:800;"
                >
                    {{ $propertyName }}
                </div>

                <div style="margin-top:4px;">
                    Attn: {{ $managerName }}
                </div>

                @if ($address)
                    <div>{{ $address }}</div>
                @endif

                @if ($location)
                    <div>{{ $location }}</div>
                @endif

                @if ($propertyEmail)
                    <div>{{ $propertyEmail }}</div>
                @endif
            </td>

            <td
                width="53%"
                valign="top"
            >
                <table width="100%">
                    <tr>
                        <td
                            style="padding:3px 0;font-weight:800;"
                        >
                            Account reference
                        </td>

                        <td
                            class="right"
                            style="font-weight:800;"
                        >
                            {{ $accountReference }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding:3px 0;font-weight:800;"
                        >
                            Invoice reference
                        </td>

                        <td
                            class="right"
                            style="font-weight:800;"
                        >
                            {{ $invoiceReference }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding:3px 0;font-weight:800;"
                        >
                            Invoice currency
                        </td>

                        <td
                            class="right"
                            style="font-weight:800;"
                        >
                            {{ $currency }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding:3px 0;font-weight:800;"
                        >
                            Invoice date
                        </td>

                        <td
                            class="right"
                            style="font-weight:800;"
                        >
                            {{ $invoiceDate }}
                        </td>
                    </tr>
                </table>

                <table
                    class="summary-table"
                    style="margin-top:10px;"
                >
                    <tr class="summary-soft">
                        <td>
                            Balance carried forward
                        </td>

                        <td class="right">
                            {{ $currency }}
                            {{ number_format(
                                $balanceCarriedForward,
                                0
                            ) }}
                        </td>
                    </tr>

                    <tr class="summary-strong">
                        <td>This invoice</td>

                        <td class="right">
                            {{ $currency }}
                            {{ number_format(
                                $invoiceTotal,
                                0
                            ) }}
                        </td>
                    </tr>

                    <tr class="summary-due">
                        <td>
                            This invoice due by
                        </td>

                        <td class="right">
                            {{ $dueDate }}
                        </td>
                    </tr>

                    <tr>
                        <td style="font-weight:800;">
                            Total balance
                        </td>

                        <td
                            class="right"
                            style="font-weight:800;"
                        >
                            {{ $currency }}
                            {{ number_format(
                                $totalBalance,
                                0
                            ) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table
        width="100%"
        style="margin-top:28px;"
    >
        <tr>
            <td
                width="49%"
                valign="top"
            >
                <div class="box-title">
                    Invoice comments
                </div>

                <div style="padding:9px;">
                    Monthly invoice for Digital Growth, Marketing, Channel Distribution, and Hotel Technology Consultancy services for {{ $propertyName }}.
                </div>
            </td>

            <td width="2%"></td>

            <td
                width="49%"
                valign="top"
            >
                <div class="section-title">
                    Summary of this invoice
                </div>

                <table
                    width="100%"
                    style="margin-top:8px;"
                >
                    <tr>
                        <td>New charges:</td>

                        <td class="right">
                            {{ $currency }}
                            {{ number_format(
                                $amountValue,
                                0
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>VAT ({{ number_format($vatRate, 0) }}%):</td>

                        <td class="right">
                            {{ $currency }}
                            {{ number_format(
                                $vat,
                                0
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>Adjustments:</td>

                        <td class="right">
                            {{ $currency }}
                            {{ number_format(
                                $adjustments,
                                0
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>Credits:</td>

                        <td class="right">
                            -{{ $currency }}
                            {{ number_format(
                                $credits,
                                0
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="border-top:1px solid #111827;"
                        >
                            Payments:
                        </td>

                        <td
                            class="right"
                            style="border-top:1px solid #111827;"
                        >
                            -{{ $currency }}
                            {{ number_format(
                                $payments,
                                0
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="border-top:1px solid #111827;font-weight:800;"
                        >
                            This invoice total:
                        </td>

                        <td
                            class="right"
                            style="border-top:1px solid #111827;font-weight:800;"
                        >
                            {{ $currency }}
                            {{ number_format(
                                $invoiceTotal,
                                0
                            ) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div
        class="section-title"
        style="margin-top:30px;"
    >
        How to pay your invoice
    </div>

    <div
        class="box-title"
        style="margin-top:7px;"
    >
        Invoice payment options
    </div>

    <div class="payment-box">
        <div
            style="font-size:12px;font-weight:800;"
        >
            Pay securely by Bank Card
        </div>
        <div style="margin-top:6px;font-weight:800;">SWIFT/BIC code: IMRWRWRWXXX</div>

        <div style="margin-top:6px;">
            Use our secure payment page to pay using
            Visa or Mastercard.
        </div>

        <table
            role="presentation"
            cellpadding="0"
            cellspacing="0"
            style="margin-top:12px;"
        >
            <tr>
                <td
                    style="background:#F9A800;padding:11px 22px;text-align:center;"
                >
                    <a
                        href="{{ $paymentUrl }}"
                        style="display:block;color:#ffffff;text-decoration:none;font-size:11px;font-weight:800;"
                    >
                        Pay with Bank Card
                    </a>
                </td>
            </tr>
        </table>

        <div
            style="margin-top:20px;font-size:12px;font-weight:800;"
        >
            Bank Transfer Details
        </div>

        <table
            width="100%"
            cellpadding="0"
            cellspacing="0"
            style="margin-top:8px;"
        >
            <tr>
                <td
                    width="38%"
                    style="padding:5px 0;color:#6b7280;"
                >
                    Bank name
                </td>

                <td
                    width="62%"
                    style="padding:5px 0;font-weight:800;"
                >
                    I&amp;M BANK Rwanda
                </td>
            </tr>

            <tr>
                <td
                    style="padding:5px 0;color:#6b7280;"
                >
                    Account name
                </td>

                <td
                    style="padding:5px 0;font-weight:800;"
                >
                    African Safari &amp; Hotel Booking Hub Ltd
                </td>
            </tr>

            <tr>
                <td
                    style="padding:5px 0;color:#6b7280;"
                >
                    Account number
                </td>

                <td
                    style="padding:5px 0;font-weight:800;"
                >
                    20149677001
                </td>
            </tr>

            <tr>
                <td
                    style="padding:5px 0;color:#6b7280;"
                >
                    TIN
                </td>

                <td
                    style="padding:5px 0;font-weight:800;"
                >
                    147893300
                </td>
            </tr>
        </table>

        <div
            style="margin-top:12px;padding-top:10px;border-top:1px solid #d1d5db;font-size:9px;color:#6b7280;"
        >
            Please use the property name or invoice
            reference as the bank transfer payment
            reference.
        </div>
    </div>

    <div class="page-break"></div>

    <table width="100%">
        <tr>
            <td
                width="52%"
                valign="top"
            >
                @if (is_file($logoPath))
                    <img
                        src="{{ $logoPath }}"
                        alt="ASHBHUB"
                        style="width:105px;height:auto;"
                    >
                @else
                    <div
                        style="font-size:20px;font-weight:900;color:#F9A800;"
                    >
                        ASHBHUB
                    </div>
                @endif
            </td>

            <td
                width="48%"
                valign="top"
                class="right"
            >


                <div class="small muted">
                    Kigali, Rwanda
                </div>

                <div class="small muted">
                    +250 788 471 880
                </div>
            </td>
        </tr>
    </table>

    <div
        class="section-title"
        style="margin-top:34px;font-size:14px;"
    >
        Invoice details
    </div>

    <table class="detail-table">
        <thead>
            <tr>
                <th width="48%">
                    New charges
                </th>

                <th width="30%">
                    Billing period
                </th>

                <th
                    width="22%"
                    class="right"
                >
                    Total
                </th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>
                    Property management service -
                    {{ $propertyName }}
                </td>

                <td>
                    {{ $billingStart }}
                    -
                    {{ $billingEnd }}
                </td>

                <td class="right">
                    {{ $currency }}
                    {{ number_format(
                        $amountValue,
                        0
                    ) }}
                </td>
            </tr>

            <tr>
                <td
                    colspan="2"
                    class="right"
                    style="font-weight:800;"
                >
                    Subtotal:
                </td>

                <td
                    class="right"
                    style="font-weight:800;"
                >
                    {{ $currency }}
                    {{ number_format(
                        $invoiceTotal,
                        0
                    ) }}
                </td>
            </tr>
        </tbody>
    </table>

    <div
        class="box-title"
        style="margin-top:28px;"
    >
        Important information
    </div>

    <div
        style="padding:12px;background:#fffaf5;border-bottom:1px solid #111827;"
    >
        <p style="margin:0;">
            Prices shown on this invoice exclude
            additional taxes or transaction fees
            unless otherwise stated.
        </p>

        <p style="margin:10px 0 0;">
            Please settle the outstanding amount
            by the due date shown on page 1.
        </p>

        
        <p style="margin:10px 0 0;">
            Contact the
            <a
                href="https://www.ashbhub.com/contact"
                style="color:#ea580c;text-decoration:underline;font-weight:700;"
            >African Safari &amp; Hotel Booking Hub billing team</a>
            when you need clarification, a payment arrangement,
            or payment confirmation.
        </p>

    </div>
</body>
</html>

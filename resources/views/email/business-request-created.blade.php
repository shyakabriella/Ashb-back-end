@php
    use Illuminate\Support\Str;

    $requestTypeLabels = [
        'salary' => 'Salary Request',
        'maintenance' => 'Maintenance Request',
        'purchase' => 'Purchase Request',
        'project' => 'Project Request',
        'marketing' => 'Marketing Request',
        'utilities' => 'Utilities Request',
        'other' => 'Other Request',
    ];

    $priorityLabels = [
        'normal' => 'Normal Priority',
        'elevated' => 'Elevated Priority',
        'urgent' => 'Urgent Execution',
    ];

    $requestType = $requestTypeLabels[$businessRequest->request_type] ?? Str::headline($businessRequest->request_type);
    $priority = $priorityLabels[$businessRequest->priority] ?? Str::headline($businessRequest->priority);

    $requesterName = $businessRequest->requester
        ? trim(($businessRequest->requester->first_name ?? '') . ' ' . ($businessRequest->requester->last_name ?? ''))
        : '';

    if ($requesterName === '' && $businessRequest->requester) {
        $requesterName = $businessRequest->requester->email;
    }

    $propertyName = $businessRequest->property?->title
        ?: $businessRequest->property_name
        ?: 'Not selected';

    $amount = number_format((float) $businessRequest->amount, 0);
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New ASHBHUB Request Created</title>
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc; padding:24px 0;">
        <tr>
            <td align="center">
                <table width="640" cellpadding="0" cellspacing="0" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden;">
                    <tr>
                        <td style="background:#0f172a; padding:28px 32px;">
                            <p style="margin:0; color:#fb923c; font-size:11px; font-weight:800; letter-spacing:2px; text-transform:uppercase;">
                                ASHBHUB Management System
                            </p>
                            <h1 style="margin:8px 0 0; color:#ffffff; font-size:24px; line-height:32px;">
                                New Request Created
                            </h1>
                            <p style="margin:8px 0 0; color:#cbd5e1; font-size:14px;">
                                A new request has been submitted and is waiting for review.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-bottom:16px;">
                                        <p style="margin:0; color:#64748b; font-size:11px; font-weight:800; letter-spacing:1.5px; text-transform:uppercase;">
                                            Request ID
                                        </p>
                                        <p style="margin:4px 0 0; color:#0f172a; font-size:18px; font-weight:800;">
                                            {{ $businessRequest->request_code }}
                                        </p>
                                    </td>
                                    <td align="right" style="padding-bottom:16px;">
                                        <span style="display:inline-block; background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; border-radius:999px; padding:8px 14px; font-size:11px; font-weight:800; text-transform:uppercase;">
                                            {{ $priority }}
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:14px; overflow:hidden;">
                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; width:38%; color:#64748b; font-size:12px; font-weight:800; text-transform:uppercase;">
                                        Title
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px; font-weight:700;">
                                        {{ $businessRequest->title }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#64748b; font-size:12px; font-weight:800; text-transform:uppercase;">
                                        Request Type
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px;">
                                        {{ $requestType }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#64748b; font-size:12px; font-weight:800; text-transform:uppercase;">
                                        Property
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px;">
                                        {{ $propertyName }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#64748b; font-size:12px; font-weight:800; text-transform:uppercase;">
                                        Estimated Amount
                                    </td>
                                    <td style="padding:14px 16px; font-size:16px; font-weight:800; color:#ea580c;">
                                        RWF {{ $amount }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#64748b; font-size:12px; font-weight:800; text-transform:uppercase;">
                                        Expected Date
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px;">
                                        {{ optional($businessRequest->expected_date)->format('Y-m-d') ?: 'Not selected' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#64748b; font-size:12px; font-weight:800; text-transform:uppercase;">
                                        Requested By
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px;">
                                        {{ $requesterName ?: 'Unknown user' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#64748b; font-size:12px; font-weight:800; text-transform:uppercase;">
                                        Status
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px; font-weight:800;">
                                        {{ Str::headline($businessRequest->status) }}
                                    </td>
                                </tr>
                            </table>

                            <div style="margin-top:22px;">
                                <p style="margin:0 0 8px; color:#64748b; font-size:11px; font-weight:800; letter-spacing:1.5px; text-transform:uppercase;">
                                    Description
                                </p>
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:16px; color:#334155; font-size:14px; line-height:22px; white-space:pre-line;">
                                    {{ $businessRequest->description ?: 'No description provided.' }}
                                </div>
                            </div>

                            <div style="margin-top:24px; background:#fff7ed; border:1px solid #fed7aa; border-radius:14px; padding:16px;">
                                <p style="margin:0; color:#9a3412; font-size:13px; line-height:21px; font-weight:700;">
                                    Please review this request in the ASHBHUB dashboard. If approved, it will be converted into an expense and will affect the property expense balance.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; background:#f8fafc; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; color:#94a3b8; font-size:12px; text-align:center;">
                                This email was generated automatically by ASHBHUB Management System.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0; color:#94a3b8; font-size:12px;">
                    © {{ date('Y') }} ASHBHUB. All rights reserved.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
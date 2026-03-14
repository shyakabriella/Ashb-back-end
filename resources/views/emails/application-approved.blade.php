<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Approved</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f1e7; font-family:Arial, Helvetica, sans-serif; color:#222;">
    @php
        $logoPath = public_path('images/logo.png');
        $company = $companyName ?? 'Africa Safari';
        $email = $companyEmail ?? 'info@africasafari.com';
        $website = $companyWebsite ?? 'https://www.africasafari.com';
    @endphp

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f6f1e7; padding:30px 15px;">
        <tr>
            <td align="center">
                <table width="700" cellpadding="0" cellspacing="0" style="max-width:700px; width:100%; background:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #e5dcc8;">

                    <tr>
                        <td style="padding:30px 30px 20px 30px; border-bottom:1px solid #eadfc9; background:#fbf7ef;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle; width:110px;">
                                        @if(file_exists($logoPath))
                                            <img src="{{ $message->embed($logoPath) }}" alt="{{ $company }} Logo" style="max-width:80px; height:auto; display:block;">
                                        @else
                                            <div style="width:80px; height:80px; border:1px solid #d6c8ab; text-align:center; line-height:80px; font-size:12px; color:#8b6f3d;">
                                                Logo
                                            </div>
                                        @endif
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <h2 style="margin:0; font-size:24px; color:#3a2f1f;">{{ $company }}</h2>
                                        <p style="margin:6px 0 0 0; font-size:14px; color:#7c6a4d;">
                                            Application Approval Notification
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 18px 0; font-size:15px;">Dear <strong>{{ $fullName }}</strong>,</p>

                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.7;">
                                Congratulations! We are pleased to inform you that your application has been
                                <strong>approved</strong>.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:12px; border:1px solid #e5dcc8; background:#fbf7ef; width:180px;"><strong>Program</strong></td>
                                    <td style="padding:12px; border:1px solid #e5dcc8;">{{ $programTitle }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px; border:1px solid #e5dcc8; background:#fbf7ef;"><strong>Shift</strong></td>
                                    <td style="padding:12px; border:1px solid #e5dcc8;">{{ $shiftName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px; border:1px solid #e5dcc8; background:#fbf7ef;"><strong>Status</strong></td>
                                    <td style="padding:12px; border:1px solid #e5dcc8; color:#15803d;"><strong>Accepted</strong></td>
                                </tr>
                            </table>

                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.7;">
                                We are happy to welcome you. Our team will contact you soon with the next instructions and onboarding details.
                            </p>

                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.7;">
                                Please keep checking your email for further communication.
                            </p>

                            <p style="margin:30px 0 0 0; font-size:15px;">
                                Best regards,<br>
                                <strong>{{ $company }}</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 30px; background:#fbf7ef; border-top:1px solid #eadfc9; font-size:13px; color:#7c6a4d; text-align:center;">
                            <div>{{ $company }}</div>
                            <div style="margin-top:6px;">Email: {{ $email }}</div>
                            <div style="margin-top:6px;">Website: {{ $website }}</div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
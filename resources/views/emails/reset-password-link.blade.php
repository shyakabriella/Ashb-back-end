<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Your Password</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f1e7; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f6f1e7; padding:30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background:#ffffff; border-radius:18px; overflow:hidden; border:1px solid #e5dcc8;">
                    <tr>
                        <td style="padding:32px; background:linear-gradient(135deg, #5b6b3c, #8b6f3d);">
                            <p style="margin:0; font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#f8e7b5; font-weight:bold;">
                                {{ $appName ?? 'Africa Safari' }}
                            </p>

                            <h1 style="margin:14px 0 0; font-size:28px; line-height:1.3; color:#ffffff;">
                                Reset Your Password
                            </h1>

                            <p style="margin:14px 0 0; font-size:15px; line-height:1.8; color:#f5f1e8;">
                                Hello {{ $user->name ?? 'User' }},
                            </p>

                            <p style="margin:14px 0 0; font-size:15px; line-height:1.8; color:#f5f1e8;">
                                We received a request to reset your password. Click the button below to open the secure password reset page.
                            </p>

                            <div style="margin:28px 0;">
                                <a href="{{ $resetUrl }}"
                                   style="display:inline-block; background-color:#f4c95d; color:#2f2416; text-decoration:none; font-size:15px; font-weight:bold; padding:14px 24px; border-radius:12px;">
                                    Reset My Password
                                </a>
                            </div>

                            <p style="margin:0; font-size:14px; line-height:1.8; color:#f5f1e8;">
                                If the button does not work, copy and paste this link into your browser:
                            </p>

                            <p style="margin:12px 0 0; font-size:13px; line-height:1.8; color:#fff3cf; word-break:break-all;">
                                {{ $resetUrl }}
                            </p>

                            <p style="margin:24px 0 0; font-size:14px; line-height:1.8; color:#f5f1e8;">
                                If you did not request a password reset, you can safely ignore this email.
                            </p>

                            <p style="margin:24px 0 0; font-size:14px; line-height:1.8; color:#f5f1e8;">
                                Thank you,<br>
                                <strong>{{ $appName ?? 'Africa Safari' }} Team</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; background:#f8f3e8; border-top:1px solid #eadfc9;">
                            <p style="margin:0; font-size:12px; line-height:1.7; color:#6b7280;">
                                This password reset email was sent from {{ $appName ?? 'Africa Safari' }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
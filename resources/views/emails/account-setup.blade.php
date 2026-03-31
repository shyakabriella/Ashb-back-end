<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Up Your Account</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f1e7; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f6f1e7; padding:30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background:#ffffff; border-radius:18px; overflow:hidden; border:1px solid #e5dcc8;">
                    <tr>
                        <td style="padding:32px; background:linear-gradient(135deg, #599e1a, #ab5b00);">
                            <p style="margin:0; font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#f8e7b5; font-weight:bold;">
                                {{ $appName ?? 'Africa Safari' }}
                            </p>
                            <center>

                            <h style="margin:14px 0 0; font-size:28px; line-height:1.3; color:#ffffff;">
                                Welcome, <br> to <br> {{ $appName ?? 'Africa Safari' }}
                            </h>

                            </center>

                            <p style="margin:14px 0 0; font-size:15px; line-height:1.8; color:#f5f1e8;">
                                Hello {{ $user->name ?? 'User' }},
                            </p>

                            <p style="margin:14px 0 0; font-size:15px; line-height:1.8; color:#f5f1e8;">
                                Your account has been created successfully. To activate your account and create your password, please use the secure link below.
                            </p>

                            <div style="margin:28px 0;">
                                <a href="{{ $resetUrl }}"
                                   style="display:inline-block; background-color:#f4c95d; color:#2f2416; text-decoration:none; font-size:15px; font-weight:bold; padding:14px 24px; border-radius:12px;">
                                    Set My Password
                                </a>
                            </div>

                            <p style="margin:0; font-size:14px; line-height:1.8; color:#f5f1e8;">
                                After setting your password, you can sign in and start using your account.
                            </p>

                            @isset($loginUrl)
                                <p style="margin:14px 0 0; font-size:14px; line-height:1.8; color:#f5f1e8;">
                                    Login page:
                                </p>
                                <p style="margin:8px 0 0; font-size:13px; line-height:1.8; color:#fff3cf; word-break:break-all;">
                                    {{ $loginUrl }}
                                </p>
                            @endisset

                            <p style="margin:14px 0 0; font-size:14px; line-height:1.8; color:#f5f1e8;">
                                If the button does not work, copy and paste this link into your browser:
                            </p>

                            <p style="margin:12px 0 0; font-size:13px; line-height:1.8; color:#fff3cf; word-break:break-all;">
                                {{ $resetUrl }}
                            </p>

                            <p style="margin:24px 0 0; font-size:14px; line-height:1.8; color:#f5f1e8;">
                                For security reasons, please use this link as soon as possible.
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
                                This email was sent because an account was created for you on {{ $appName ?? 'Africa Safari' }}.
                                If you were not expecting this email, please contact support.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
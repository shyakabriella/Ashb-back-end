<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Your Account</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f1e7; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f6f1e7; margin:0; padding:30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background-color:#ffffff; border:1px solid #e5dcc8; border-radius:18px; overflow:hidden;">
                    
                    <!-- Top Accent -->
                    <tr>
                        <td style="height:8px; background:linear-gradient(90deg, #fda400, #fda400); font-size:0; line-height:0;">&nbsp;</td>
                    </tr>

                    <!-- Logo -->
                    <tr>
                        <td align="center" style="padding:32px 32px 12px 32px; background-color:#ffffff;">
                            <a href="https://www.ashbhub.com/" target="_blank" style="text-decoration:none; display:inline-block;">
                                <img
                                    src="{{ $message->embed(public_path('ash.png')) }}"
                                    alt="{{ $appName ?? 'Africa Safari' }} Logo"
                                    style="max-width:180px; height:auto; display:block; margin:0 auto; border:0;"
                                >
                            </a>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding:12px 32px 32px 32px; background-color:#ffffff;">
                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.8; color:#1f2937; text-align:left;">
                                Hello {{ $user->name ?? 'User' }},
                            </p>

                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.8; color:#374151; text-align:left;">
                                Your account has been created successfully. To activate your account and create your password, please use the secure link below.
                            </p>

                            <div style="margin:0 0 24px 0; text-align:left;">
                                <a href="{{ $resetUrl }}"
                                   style="display:inline-block; background-color:#fda400; color:#2f2416; text-decoration:none; font-size:15px; font-weight:bold; padding:14px 26px; border-radius:12px;">
                                    Set My Password
                                </a>
                            </div>

                            <p style="margin:0 0 18px 0; font-size:14px; line-height:1.8; color:#374151; text-align:left;">
                                After setting your password, you can sign in and start using your account.
                            </p>

                            @isset($loginUrl)
                                <div style="margin:0 0 20px 0; padding:16px 18px; background-color:#faf7f0; border:1px solid #eadfc9; border-radius:12px;">
                                    <p style="margin:0 0 8px 0; font-size:14px; line-height:1.7; color:#1f2937; font-weight:bold; text-align:left;">
                                        Login page
                                    </p>
                                    <p style="margin:0; font-size:13px; line-height:1.8; color:#0000EE; word-break:break-all; text-align:left;">
                                        <a href="{{ $loginUrl }}" style="color:#0000EE; text-decoration:underline;">
                                            {{ $loginUrl }}
                                        </a>
                                    </p>
                                </div>
                            @endisset

                            <p style="margin:0 0 10px 0; font-size:14px; line-height:1.8; color:#374151; text-align:left;">
                                If the button does not work, copy and paste this link into your browser:
                            </p>

                            <p style="margin:0 0 20px 0; font-size:13px; line-height:1.8; color:#0000EE; word-break:break-all; text-align:left;">
                                <a href="{{ $resetUrl }}" style="color:#0000EE; text-decoration:underline;">
                                    {{ $resetUrl }}
                                </a>
                            </p>

                            <p style="margin:0 0 18px 0; font-size:14px; line-height:1.8; color:#374151; text-align:left;">
                                For security reasons, please use this link as soon as possible.
                            </p>

                            <p style="margin:0; font-size:14px; line-height:1.8; color:#1f2937; text-align:left;">
                                Thank you,<br>
                                <strong>{{ $appName ?? 'Africa Safari' }} Team</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:18px 32px; background-color:#f8f3e8; border-top:1px solid #eadfc9;">
                            <p style="margin:0; font-size:12px; line-height:1.7; color:#6b7280; text-align:left;">
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
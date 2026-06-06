<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        http-equiv="Content-Type"
        content="text/html; charset=UTF-8"
    >

    <title>Reset Your Password</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background-color: #f6f1e7;
        font-family: Arial, Helvetica, sans-serif;
        color: #1f2937;
    "
>
    @php
        $logoPath = public_path('ash.png');
        $logoData = null;

        if (is_file($logoPath) && is_readable($logoPath)) {
            $logoContents = file_get_contents($logoPath);

            if ($logoContents !== false) {
                $logoData = $logoContents;
            }
        }

        $firstName = trim((string) ($user->first_name ?? ''));
        $lastName = trim((string) ($user->last_name ?? ''));

        $fullName = trim($firstName . ' ' . $lastName);

        $displayName = $fullName !== ''
            ? $fullName
            : trim((string) ($user->name ?? 'User'));

        $applicationName = $appName
            ?? 'African Safari & Hotel Booking Hub';

        $expirationMinutes = $expiresIn ?? 60;
    @endphp

    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="
            width: 100%;
            margin: 0;
            padding: 30px 15px;
            background-color: #f6f1e7;
        "
    >
        <tr>
            <td align="center">
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 640px;
                        overflow: hidden;
                        background-color: #ffffff;
                        border: 1px solid #e5dcc8;
                        border-radius: 18px;
                    "
                >
                    {{-- Top accent --}}
                    <tr>
                        <td
                            style="
                                height: 8px;
                                background-color: #fda400;
                                font-size: 0;
                                line-height: 0;
                            "
                        >
                            &nbsp;
                        </td>
                    </tr>

                    {{-- Logo --}}
                    <tr>
                        <td
                            align="center"
                            style="
                                padding: 32px 32px 12px 32px;
                                background-color: #ffffff;
                            "
                        >
                            <a
                                href="https://www.ashbhub.com/"
                                target="_blank"
                                style="
                                    display: inline-block;
                                    text-decoration: none;
                                "
                            >
                                @if ($logoData !== null)
                                    <img
                                        src="{{ $message->embedData(
                                            $logoData,
                                            'ash.png',
                                            'image/png'
                                        ) }}"
                                        alt="{{ $applicationName }} Logo"
                                        width="180"
                                        style="
                                            display: block;
                                            width: 100%;
                                            max-width: 180px;
                                            height: auto;
                                            margin: 0 auto;
                                            border: 0;
                                            outline: none;
                                            text-decoration: none;
                                        "
                                    >
                                @else
                                    <div
                                        style="
                                            font-size: 23px;
                                            line-height: 1.4;
                                            font-weight: bold;
                                            color: #1f2937;
                                            text-align: center;
                                        "
                                    >
                                        {{ $applicationName }}
                                    </div>
                                @endif
                            </a>
                        </td>
                    </tr>

                    {{-- Main content --}}
                    <tr>
                        <td
                            style="
                                padding: 12px 32px 32px 32px;
                                background-color: #ffffff;
                            "
                        >
                            <p
                                style="
                                    margin: 0 0 8px 0;
                                    color: #f59e0b;
                                    font-size: 12px;
                                    line-height: 1.5;
                                    font-weight: bold;
                                    letter-spacing: 1.5px;
                                    text-transform: uppercase;
                                    text-align: left;
                                "
                            >
                                Password assistance
                            </p>

                            <h1
                                style="
                                    margin: 0 0 20px 0;
                                    color: #1f2937;
                                    font-size: 27px;
                                    line-height: 1.3;
                                    font-weight: bold;
                                    text-align: left;
                                "
                            >
                                Reset Your Password
                            </h1>

                            <p
                                style="
                                    margin: 0 0 18px 0;
                                    color: #1f2937;
                                    font-size: 15px;
                                    line-height: 1.8;
                                    text-align: left;
                                "
                            >
                                Hello {{ $displayName }},
                            </p>

                            <p
                                style="
                                    margin: 0 0 20px 0;
                                    color: #374151;
                                    font-size: 15px;
                                    line-height: 1.8;
                                    text-align: left;
                                "
                            >
                                We received a request to reset the password for
                                your account. Click the button below to create
                                a new password.
                            </p>

                            {{-- Reset password button --}}
                            <table
                                role="presentation"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                                style="
                                    margin: 0 0 24px 0;
                                "
                            >
                                <tr>
                                    <td
                                        align="center"
                                        bgcolor="#FDA400"
                                        style="
                                            background-color: #fda400;
                                            border-radius: 12px;
                                        "
                                    >
                                        <a
                                            href="{{ $resetUrl }}"
                                            target="_blank"
                                            style="
                                                display: inline-block;
                                                padding: 14px 26px;
                                                background-color: #fda400;
                                                border-radius: 12px;
                                                color: #2f2416;
                                                font-size: 15px;
                                                line-height: 1.2;
                                                font-weight: bold;
                                                text-decoration: none;
                                            "
                                        >
                                            Reset My Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Expiration notice --}}
                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                                style="
                                    width: 100%;
                                    margin: 0 0 20px 0;
                                    background-color: #fff8e8;
                                    border: 1px solid #f5dca0;
                                    border-radius: 12px;
                                "
                            >
                                <tr>
                                    <td
                                        style="
                                            padding: 14px 18px;
                                        "
                                    >
                                        <p
                                            style="
                                                margin: 0;
                                                color: #7c5b12;
                                                font-size: 13px;
                                                line-height: 1.7;
                                                text-align: left;
                                            "
                                        >
                                            For your security, this password
                                            reset link will expire in
                                            <strong>
                                                {{ $expirationMinutes }} minutes
                                            </strong>.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p
                                style="
                                    margin: 0 0 10px 0;
                                    color: #374151;
                                    font-size: 14px;
                                    line-height: 1.8;
                                    text-align: left;
                                "
                            >
                                If the button does not work, copy and paste this
                                link into your browser:
                            </p>

                            <p
                                style="
                                    margin: 0 0 20px 0;
                                    color: #0000ee;
                                    font-size: 13px;
                                    line-height: 1.8;
                                    word-break: break-all;
                                    text-align: left;
                                "
                            >
                                <a
                                    href="{{ $resetUrl }}"
                                    target="_blank"
                                    style="
                                        color: #0000ee;
                                        text-decoration: underline;
                                    "
                                >
                                    {{ $resetUrl }}
                                </a>
                            </p>

                            <p
                                style="
                                    margin: 0 0 18px 0;
                                    color: #374151;
                                    font-size: 14px;
                                    line-height: 1.8;
                                    text-align: left;
                                "
                            >
                                If you did not request a password reset, you can
                                safely ignore this email. Your password will not
                                be changed.
                            </p>

                            <p
                                style="
                                    margin: 0;
                                    color: #1f2937;
                                    font-size: 14px;
                                    line-height: 1.8;
                                    text-align: left;
                                "
                            >
                                Thank you,<br>

                                <strong>
                                    {{ $applicationName }} Team
                                </strong>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td
                            style="
                                padding: 18px 32px;
                                background-color: #f8f3e8;
                                border-top: 1px solid #eadfc9;
                            "
                        >
                            <p
                                style="
                                    margin: 0 0 6px 0;
                                    color: #6b7280;
                                    font-size: 12px;
                                    line-height: 1.7;
                                    text-align: left;
                                "
                            >
                                This password reset email was sent from
                                {{ $applicationName }}.
                            </p>

                            <p
                                style="
                                    margin: 0;
                                    color: #9ca3af;
                                    font-size: 11px;
                                    line-height: 1.7;
                                    text-align: left;
                                "
                            >
                                This is an automated security email. Please do
                                not share the reset link with anyone.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
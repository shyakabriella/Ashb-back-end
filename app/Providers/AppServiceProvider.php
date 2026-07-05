<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Custom password reset email
        |--------------------------------------------------------------------------
        |
        | Laravel normally searches for a route named "password.reset".
        | Because the password reset page is in the Next.js frontend,
        | we manually create the frontend reset URL.
        |
        */

        ResetPassword::toMailUsing(
            function (object $notifiable, string $token): MailMessage {
                $appName = (string) config(
                    'app.name',
                    'African Safari & Hotel Booking Hub'
                );

                $frontendResetUrl = rtrim(
                    (string) config(
                        'app.frontend_reset_password_url',
                        'http://localhost:3000/reset-password'
                    ),
                    '/'
                );

                $email = method_exists(
                    $notifiable,
                    'getEmailForPasswordReset'
                )
                    ? $notifiable->getEmailForPasswordReset()
                    : (string) ($notifiable->email ?? '');

                $resetUrl = $frontendResetUrl
                    . '?token=' . urlencode($token)
                    . '&email=' . urlencode($email);

                $passwordBroker = (string) config(
                    'auth.defaults.passwords',
                    'users'
                );

                $expiresIn = (int) config(
                    'auth.passwords.' . $passwordBroker . '.expire',
                    60
                );

                return (new MailMessage)
                    ->subject('Reset Your Password - ' . $appName)
                    ->view('emails.reset-password-link', [
                        'appName' => $appName,
                        'user' => $notifiable,
                        'resetUrl' => $resetUrl,
                        'expiresIn' => $expiresIn,
                    ]);
            }
        );
    }
}
<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Custom forgot-password email
        |--------------------------------------------------------------------------
        |
        | Laravel's default reset notification searches for a Laravel route
        | named "password.reset".
        |
        | Because our password reset page is in Next.js, we create the
        | frontend reset URL manually and use our custom Blade email.
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
                    : ($notifiable->email ?? '');

                $resetUrl = $frontendResetUrl
                    . '?token=' . urlencode($token)
                    . '&email=' . urlencode((string) $email);

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
                    ->view('emails.reset-password', [
                        'appName' => $appName,
                        'user' => $notifiable,
                        'resetUrl' => $resetUrl,
                        'expiresIn' => $expiresIn,
                    ]);
            }
        );
    }
}
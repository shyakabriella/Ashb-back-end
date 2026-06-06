<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
        | Password reset frontend URL
        |--------------------------------------------------------------------------
        |
        | Laravel's default password reset notification normally searches for
        | a named route called "password.reset".
        |
        | Since this application uses a Next.js frontend, we generate the reset
        | URL manually and send the user to the Next.js reset-password page.
        |
        */

        ResetPassword::createUrlUsing(
            function (object $notifiable, string $token): string {
                $resetPageUrl = rtrim(
                    (string) config('app.frontend_reset_password_url'),
                    '/'
                );

                $email = method_exists(
                    $notifiable,
                    'getEmailForPasswordReset'
                )
                    ? $notifiable->getEmailForPasswordReset()
                    : $notifiable->email;

                return $resetPageUrl
                    . '?token=' . urlencode($token)
                    . '&email=' . urlencode((string) $email);
            }
        );
    }
}
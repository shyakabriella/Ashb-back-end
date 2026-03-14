<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $resetUrl,
        protected ?object $user = null,
        protected ?string $appName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->user ?? $notifiable;
        $appName = $this->appName ?: config('app.name', 'Africa Safari');

        return (new MailMessage)
            ->subject('Reset Your Password - ' . $appName)
            ->view('emails.reset-password-link', [
                'appName'  => $appName,
                'user'     => $user,
                'resetUrl' => $this->resetUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reset_password',
            'reset_url' => $this->resetUrl,
        ];
    }
}
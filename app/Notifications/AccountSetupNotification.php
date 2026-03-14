<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSetupNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $resetUrl,
        protected ?object $user = null,
        protected ?string $loginUrl = null,
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
            ->subject('Set Up Your Account - ' . $appName)
            ->view('emails.account-setup', [
                'appName'  => $appName,
                'user'     => $user,
                'resetUrl' => $this->resetUrl,
                'loginUrl' => $this->loginUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_setup',
            'reset_url' => $this->resetUrl,
            'login_url' => $this->loginUrl,
        ];
    }
}
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected mixed $application,
        protected ?string $companyName = null,
        protected ?string $companyEmail = null,
        protected ?string $companyWebsite = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Application Received - ' . $this->getCompanyName())
            ->view('emails.application-received', [
                'fullName'       => $this->getFullName($notifiable),
                'programTitle'   => $this->getProgramTitle(),
                'shiftName'      => $this->getShiftName(),
                'companyName'    => $this->getCompanyName(),
                'companyEmail'   => $this->getCompanyEmail(),
                'companyWebsite' => $this->getCompanyWebsite(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_received',
            'full_name' => $this->getFullName($notifiable),
            'program_title' => $this->getProgramTitle(),
            'shift_name' => $this->getShiftName(),
        ];
    }

    protected function getCompanyName(): string
    {
        return $this->companyName ?: config('app.name', 'Africa Safari');
    }

    protected function getCompanyEmail(): string
    {
        return $this->companyEmail ?: config('mail.from.address', 'info@africasafari.com');
    }

    protected function getCompanyWebsite(): string
    {
        return $this->companyWebsite ?: config('app.url', 'https://www.africasafari.com');
    }

    protected function getFullName(object $notifiable): string
    {
        $fullName = trim((string) (
            data_get($this->application, 'full_name') ?:
            trim(
                (string) data_get($this->application, 'first_name', '') . ' ' .
                (string) data_get($this->application, 'last_name', '')
            ) ?:
            data_get($this->application, 'name') ?:
            data_get($notifiable, 'name') ?:
            'Applicant'
        ));

        return $fullName !== '' ? $fullName : 'Applicant';
    }

    protected function getProgramTitle(): string
    {
        return (string) (
            data_get($this->application, 'program_title') ?:
            data_get($this->application, 'program.title') ?:
            data_get($this->application, 'program.name') ?:
            'N/A'
        );
    }

    protected function getShiftName(): string
    {
        return (string) (
            data_get($this->application, 'shift_name') ?:
            data_get($this->application, 'shift.name') ?:
            data_get($this->application, 'shift_ref') ?:
            'N/A'
        );
    }
}
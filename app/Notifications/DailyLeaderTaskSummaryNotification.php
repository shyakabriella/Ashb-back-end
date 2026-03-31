<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyLeaderTaskSummaryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $recipientName,
        public string $scheduleDate,
        public array $summaryRows,
        public string $dashboardUrl
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Daily Team Task Summary - {$this->scheduleDate}")
            ->markdown('emails.daily_leader_task_summary', [
                'recipientName' => $this->recipientName,
                'scheduleDate' => $this->scheduleDate,
                'summaryRows' => $this->summaryRows,
                'dashboardUrl' => $this->dashboardUrl,
            ]);
    }
}
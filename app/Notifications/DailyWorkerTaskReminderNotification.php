<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyWorkerTaskReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $workerName,
        public string $scheduleDate,
        public array $todayTasks,
        public array $overdueTasks,
        public string $dashboardUrl
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = empty($this->overdueTasks)
            ? "Today's Task Reminder - {$this->scheduleDate}"
            : "Task Reminder & Overdue Warning - {$this->scheduleDate}";

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.daily_worker_task_reminder', [
                'workerName' => $this->workerName,
                'scheduleDate' => $this->scheduleDate,
                'todayTasks' => $this->todayTasks,
                'overdueTasks' => $this->overdueTasks,
                'dashboardUrl' => $this->dashboardUrl,
            ]);
    }
}
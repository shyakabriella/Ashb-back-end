<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Task $task)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->task->loadMissing('property');

        $workerName = trim(
            ($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? '')
        );

        if ($workerName === '') {
            $workerName = $notifiable->name ?? 'Worker';
        }

        $propertyName = $task->property->title
            ?? $task->property->name
            ?? 'Selected Property';

        return (new MailMessage)
            ->subject('New Task Assigned: ' . $task->title)
            ->greeting('Hello ' . $workerName . ',')
            ->line('A new task has been assigned to you.')
            ->line('Property: ' . $propertyName)
            ->line('Task: ' . $task->title)
            ->line('Milestone: ' . ($task->milestone ?: 'N/A'))
            ->line('Start: ' . optional($task->start_at)->format('Y-m-d H:i'))
            ->line('End: ' . optional($task->end_at)->format('Y-m-d H:i'))
            ->line('Status: ' . ucfirst(str_replace('_', ' ', $task->status)))
            ->action('View My Tasks', url('/dashboard/tasks/my-tasks'))
            ->line('Please log in to see the task in your worker task box.');
    }
}
<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyWorkerTaskReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyWorkerTaskRemindersCommand extends Command
{
    protected $signature = 'tasks:send-daily-worker-reminders';

    protected $description = 'Send employees and interns daily reminders of today tasks and overdue tasks';

    public function handle(): int
    {
        $timezone = config('app.timezone', 'Africa/Kigali');
        $today = Carbon::now($timezone);
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay = $today->copy()->endOfDay();

        $workers = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', ['employee', 'intern']);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $dashboardUrl = rtrim((string) env('FRONTEND_APP_URL', config('app.url')), '/')
            . '/dashboard/tasks';

        foreach ($workers as $worker) {
            $todayTasks = Task::query()
                ->with('property')
                ->where('status', '!=', 'completed')
                ->whereHas('workers', function ($query) use ($worker) {
                    $query->where('users.id', $worker->id);
                })
                ->where(function ($query) use ($startOfDay, $endOfDay) {
                    $query
                        ->whereBetween('start_at', [$startOfDay, $endOfDay])
                        ->orWhereBetween('end_at', [$startOfDay, $endOfDay])
                        ->orWhere(function ($nested) use ($startOfDay, $endOfDay) {
                            $nested->where('start_at', '<', $startOfDay)
                                ->where('end_at', '>', $endOfDay);
                        });
                })
                ->orderBy('start_at')
                ->get()
                ->map(fn (Task $task) => $this->formatTaskRow($task, $timezone))
                ->values()
                ->all();

            $overdueTasks = Task::query()
                ->with('property')
                ->where('status', '!=', 'completed')
                ->where('end_at', '<', $startOfDay)
                ->whereHas('workers', function ($query) use ($worker) {
                    $query->where('users.id', $worker->id);
                })
                ->orderBy('end_at')
                ->get()
                ->map(fn (Task $task) => $this->formatTaskRow($task, $timezone))
                ->values()
                ->all();

            if (empty($todayTasks) && empty($overdueTasks)) {
                continue;
            }

            $worker->notify(new DailyWorkerTaskReminderNotification(
                workerName: $this->userDisplayName($worker),
                scheduleDate: $today->format('Y-m-d'),
                todayTasks: $todayTasks,
                overdueTasks: $overdueTasks,
                dashboardUrl: $dashboardUrl
            ));
        }

        $this->info('Daily worker reminder emails sent.');

        return self::SUCCESS;
    }

    private function formatTaskRow(Task $task, string $timezone): array
    {
        return [
            'title' => (string) $task->title,
            'property' => (string) ($task->property?->name ?? '-'),
            'milestone' => filled($task->milestone) ? (string) $task->milestone : '-',
            'time' => $this->formatTaskTime($task->start_at, $task->end_at, $timezone),
            'status' => $this->formatStatus((string) $task->status),
        ];
    }

    private function userDisplayName(User $user): string
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $name !== '' ? $name : ($user->email ?? 'User');
    }

    private function formatStatus(string $status): string
    {
        return ucwords(str_replace('_', ' ', strtolower(trim($status))));
    }

    private function formatTaskTime($startAt, $endAt, string $timezone): string
    {
        $start = $startAt ? Carbon::parse($startAt)->timezone($timezone)->format('H:i') : '--:--';
        $end = $endAt ? Carbon::parse($endAt)->timezone($timezone)->format('H:i') : '--:--';

        return "{$start} - {$end}";
    }
}
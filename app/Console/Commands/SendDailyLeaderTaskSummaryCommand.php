<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyLeaderTaskSummaryNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyLeaderTaskSummaryCommand extends Command
{
    protected $signature = 'tasks:send-daily-leader-summary';

    protected $description = 'Send CEO and MD a daily summary of employee and intern tasks';

    public function handle(): int
    {
        $timezone = config('app.timezone', 'Africa/Kigali');
        $today = Carbon::now($timezone);
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay = $today->copy()->endOfDay();

        $leaders = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', ['ceo', 'md']);
            })
            ->get();

        if ($leaders->isEmpty()) {
            $this->warn('No CEO or MD users found.');
            return self::SUCCESS;
        }

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

        $summaryRows = [];

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

            $summaryRows[] = [
                'employee_name' => $this->userDisplayName($worker),
                'role' => $this->roleLabel($worker->role?->slug),
                'today_tasks' => $todayTasks,
                'overdue_tasks' => $overdueTasks,
            ];
        }

        $dashboardUrl = rtrim((string) env('FRONTEND_APP_URL', config('app.url')), '/')
            . '/dashboard/tasks';

        foreach ($leaders as $leader) {
            $leader->notify(new DailyLeaderTaskSummaryNotification(
                recipientName: $this->userDisplayName($leader),
                scheduleDate: $today->format('Y-m-d'),
                summaryRows: $summaryRows,
                dashboardUrl: $dashboardUrl
            ));
        }

        $this->info('Daily leader summary emails sent.');

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

    private function roleLabel(?string $roleSlug): string
    {
        $roleSlug = strtolower(trim((string) $roleSlug));

        return match ($roleSlug) {
            'employee' => 'Employee',
            'intern' => 'Intern',
            'ceo' => 'CEO',
            'md' => 'MD',
            default => ucfirst(str_replace('_', ' ', $roleSlug ?: 'user')),
        };
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
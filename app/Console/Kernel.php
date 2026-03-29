<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('tasks:send-daily-leader-summary')
            ->dailyAt('06:00')
            ->timezone(config('app.timezone', 'Africa/Kigali'))
            ->withoutOverlapping();

        $schedule->command('tasks:send-daily-worker-reminders')
            ->dailyAt('06:00')
            ->timezone(config('app.timezone', 'Africa/Kigali'))
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
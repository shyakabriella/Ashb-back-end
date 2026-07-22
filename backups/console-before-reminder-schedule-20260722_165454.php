<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:send-reminders')->dailyAt('08:00');


Schedule::command(
    'invoices:send-outstanding-reminders'
)
    ->monthlyOn(1, '08:00')
    ->timezone('Africa/Kigali')
    ->withoutOverlapping(120);


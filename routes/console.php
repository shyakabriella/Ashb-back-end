<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:send-reminders')->dailyAt('08:00');
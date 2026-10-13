<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:send')->dailyAt('17:00')->timezone('Africa/Lagos')->withoutOverlapping();
Schedule::command('speaking:prune-audio')->dailyAt('03:00')->timezone('Africa/Lagos')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();

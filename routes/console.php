<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('app:cleanup-games')->dailyAt('04:00');
Schedule::command('app:cleanup-orphaned-players')->weeklyOn(0, '05:00');
// Schedule::command('beta:invite-waitlist '.config('beta.daily_invites'))->dailyAt('21:00');

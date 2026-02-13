<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:cleanup-unplayed-games')->dailyAt('04:00');
Schedule::command('app:send-beta-feedback-requests')->hourly();

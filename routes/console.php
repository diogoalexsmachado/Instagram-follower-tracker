<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('followers:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping(45)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/followers-sync.log'));

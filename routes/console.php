<?php

use App\Console\Commands\SyncPendingGenerations;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SyncPendingGenerations::class)
    ->everyMinute()
    ->withoutOverlapping();

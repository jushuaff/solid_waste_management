<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('swm:about',function(){ $this->info('Barangay SWM · Laravel + MongoDB'); });


// Run "php artisan schedule:run" every minute through Windows Task Scheduler or cron.
\Illuminate\Support\Facades\Schedule::command('linis:check-alerts')->hourly()->withoutOverlapping();

// Phase 2 household fee reminders: runs daily at 9 AM Philippine time.
// Requires php artisan schedule:run every minute (Task Scheduler / cron).
\Illuminate\Support\Facades\Schedule::command('fees:send-reminders')
    ->dailyAt('09:00')->timezone('Asia/Manila')->withoutOverlapping();

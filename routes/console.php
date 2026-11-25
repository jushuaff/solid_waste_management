<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('swm:about',function(){ $this->info('Barangay SWM · Laravel + MongoDB'); });


// Run "php artisan schedule:run" every minute through Windows Task Scheduler or cron.
\Illuminate\Support\Facades\Schedule::command('linis:check-alerts')->hourly()->withoutOverlapping();

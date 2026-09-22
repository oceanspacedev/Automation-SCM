<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nonaktifkan auto-analyze AI terjadwal di lingkungan local agar tidak otomatis berjalan
if (! app()->environment('local') || env('ENABLE_AUTO_AI_SCHEDULE', false)) {
    Schedule::command('program:auto-analyze-ai --year=2026 --limit=15 --sleep=0.2')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

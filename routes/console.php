<?php

use App\Services\DataProgramSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-sync Data Program dari Google Spreadsheet setiap 1 menit (jika scheduler aktif)
Artisan::command('data-program:sync {--limit=0}', function (DataProgramSyncService $syncService) {
    $this->info('Memulai sinkronisasi Data Program dari Google Spreadsheet...');
    try {
        $result = $syncService->sync(null, (int) $this->option('limit'));
        $this->info($result['message']);
    } catch (Throwable $e) {
        $this->error('Gagal sinkronisasi: '.$e->getMessage());
    }
})->purpose('Sinkronisasi data master Data Program (56 kolom) dari Google Spreadsheet');

// Nonaktifkan auto-sync Data Program terjadwal di lingkungan local agar tidak bentrok lock database
if (! app()->environment('local') || env('ENABLE_AUTO_SYNC_SCHEDULE', false)) {
    Schedule::command('data-program:sync')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

// Nonaktifkan auto-analyze AI terjadwal di lingkungan local agar tidak otomatis berjalan
if (! app()->environment('local') || env('ENABLE_AUTO_AI_SCHEDULE', false)) {
    Schedule::command('program:auto-analyze-ai --year=2026 --limit=15 --sleep=0.2')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

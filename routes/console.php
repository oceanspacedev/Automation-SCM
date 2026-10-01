<?php

use App\Models\ProgramSubmission;
use App\Services\DataProgramSyncService;
use App\Services\ProgramReconciliationService;
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

Artisan::command('program:refresh-completeness', function () {
    $this->info('Memperbarui status kelengkapan dokumen Non-PKP...');
    $reconciler = app(ProgramReconciliationService::class);

    $updated = 0;
    ProgramSubmission::where(function ($q) {
        $q->whereNull('tax_invoice_url')
            ->orWhere('tax_invoice_url', '')
            ->orWhere('tax_invoice_url', '-');
    })
        ->whereNotNull('credit_note_url')->where('credit_note_url', '!=', '')
        ->whereNotNull('agreement_url')->where('agreement_url', '!=', '')
        ->chunkById(200, function ($subs) use ($reconciler, &$updated) {
            foreach ($subs as $sub) {
                $isPkp = ((float) ($sub->ppn ?? 0) > 0) || $reconciler->isPkpFromSubmission($sub);
                if (! $isPkp) {
                    // Non-PKP with both CN and Agr
                    $hasIssue = false;
                    if ($sub->doc_validation) {
                        $cn = $sub->doc_validation['cn'] ?? [];
                        $agr = $sub->doc_validation['agr'] ?? [];
                        if (($cn['status'] ?? '') === 'invalid' || ($cn['status'] ?? '') === 'swapped' ||
                            ($agr['status'] ?? '') === 'invalid' || ($agr['status'] ?? '') === 'swapped') {
                            $hasIssue = true;
                        }
                    }
                    if (! $hasIssue && $sub->status_potong_purchase !== 'BISA DI POTONG' && $sub->status_potong_purchase !== 'SUDAH POTONG') {
                        $sub->update([
                            'status_potong_purchase' => 'BISA DI POTONG',
                            'cek_dokumen' => 'LENGKAP',
                        ]);
                        $updated++;
                    }
                }
            }
        });

    $this->info("Selesai! Sebanyak {$updated} pengajuan Non-PKP diperbarui menjadi BISA DI POTONG.");
})->purpose('Refresh status potong purchase untuk pengajuan Non-PKP yang memiliki CN dan Agreement');

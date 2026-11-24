<?php

use App\Models\AutomationSchedule;
use App\Models\ProgramSubmission;
use App\Services\DataProgramSyncService;
use App\Services\ProgramReconciliationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

Schedule::call(static function (): void {
    AutomationSchedule::recordSchedulerHeartbeat();
})
    ->name('scheduler-heartbeat')
    ->everyMinute();

// Sinkron Data Program dari Google Spreadsheet hanya bila dinyalakan (ENABLE_AUTO_SYNC_SCHEDULE=true): sheet menimpa isi
// Data Program, termasuk tanggal & nomor yang sudah dikelola di aplikasi
if (config('services.data_program.auto_sync')) {
    Schedule::command('data-program:sync')
        ->everyMinute()
        ->withoutOverlapping(10)
        ->runInBackground()
        ->onFailure(static function (): void {
            Log::warning('Scheduled command data-program:sync gagal dijalankan.');
        });
}

// Nonaktifkan auto-analyze AI terjadwal di lingkungan local agar tidak otomatis berjalan.
// Hanya spawn proses child jika memang ada submission 2026 yang belum dianalisis agar RAM/CPU tidak melonjak tiap menit.
if (! app()->environment('local') || env('ENABLE_AUTO_AI_SCHEDULE', false)) {
    Schedule::command('program:auto-analyze-ai --year=2026 --limit=15 --sleep=0.2')
        ->everyMinute()
        ->when(static function (): bool {
            try {
                return ProgramSubmission::withoutGlobalScopes(['area', 'brand'])
                    ->where('submission_timestamp', 'like', '2026%')
                    ->where(static function ($q): void {
                        $q->whereNull('cek_dokumen')
                            ->orWhere('cek_dokumen', '')
                            ->orWhereNull('status_potong_purchase')
                            ->orWhere('status_potong_purchase', '');
                    })
                    ->exists();
            } catch (Throwable $e) {
                Log::warning("Gagal cek antrean AI di scheduler: {$e->getMessage()}");

                return false;
            }
        })
        ->withoutOverlapping(15)
        ->runInBackground()
        ->onFailure(static function (): void {
            Log::warning('Scheduled command program:auto-analyze-ai gagal dijalankan.');
        });
}

// Cocokkan otomatis Data Program ↔ Form Program setiap menit (tanpa WhatsApp).
Schedule::command('program:auto-match')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->onFailure(static function (): void {
        Log::warning('Scheduled command program:auto-match gagal dijalankan.');
    });

// Jalankan antrean (mis. import Master Dealer dari web) di server yang hanya memasang cron scheduler, tanpa queue worker sendiri.
// Hanya spawn proses queue:work ketika ada job siap dijalankan agar tidak membuka proses PHP sia-sia tiap menit.
Schedule::command('queue:work --stop-when-empty --tries=1 --timeout=3600 --memory=256')
    ->everyMinute()
    ->when(static function (): bool {
        try {
            $connection = (string) config('queue.default', 'database');
            $driver = config("queue.connections.{$connection}.driver");

            if ($driver === 'sync' || $driver === 'null') {
                return false;
            }

            if ($driver !== 'database') {
                return true;
            }

            $dbConnection = config("queue.connections.{$connection}.connection");
            $table = (string) config("queue.connections.{$connection}.table", 'jobs');

            return DB::connection($dbConnection)
                ->table($table)
                ->where('available_at', '<=', now()->timestamp)
                ->exists();
        } catch (Throwable $e) {
            Log::warning("Gagal cek antrean queue di scheduler: {$e->getMessage()}");

            return false;
        }
    })
    ->withoutOverlapping(65)
    ->runInBackground()
    ->onFailure(static function (): void {
        Log::warning('Scheduled command queue:work gagal dijalankan.');
    });

// WhatsApp ke telemarketing untuk baris yang BISA DI POTONG; jamnya diatur di Master → Jadwal Otomatis (dicek tiap menit).
Schedule::command('program:notify-telemarketing')
    ->everyMinute()
    ->when(static fn (): bool => AutomationSchedule::isDueNow(AutomationSchedule::TELEMARKETING_WA))
    ->withoutOverlapping(15)
    ->runInBackground()
    ->onFailure(static function (): void {
        Log::warning('Scheduled command program:notify-telemarketing gagal dijalankan.');
    });

// WhatsApp pengingat ke telemarketing untuk invoice yang belum di-upload ke Form Program; jamnya diatur di Master → Jadwal Otomatis.
Schedule::command('invoices:remind-telemarketing-upload')
    ->everyMinute()
    ->when(static fn (): bool => AutomationSchedule::isDueNow(AutomationSchedule::INVOICE_REMIND_UPLOAD))
    ->withoutOverlapping(15)
    ->runInBackground()
    ->onFailure(static function (): void {
        Log::warning('Scheduled command invoices:remind-telemarketing-upload gagal dijalankan.');
    });

// Perbarui otomatis Lama Pending (selisih hari ini - Tgl Share CN) & Keterangan aging setiap hari pukul 00:01 WIB
Schedule::command('program:update-pending')
    ->dailyAt('00:01')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->onFailure(static function (): void {
        Log::warning('Scheduled command program:update-pending gagal dijalankan.');
    });

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

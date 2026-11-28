<?php

use App\Models\AutomationSchedule;
use App\Models\ProgramSubmission;
use App\Services\DataProgramSyncService;
use App\Services\ProgramReconciliationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-sync Data Program dari Google Spreadsheet setiap 1 menit (jika scheduler aktif)
Artisan::command('data-program:sync {--limit=0}', function (DataProgramSyncService $syncService): int {
    $this->info('Memulai sinkronisasi Data Program dari Google Spreadsheet...');
    try {
        $result = $syncService->sync(null, (int) $this->option('limit'));
        $this->info($result['message']);

        return 0;
    } catch (Throwable $e) {
        $this->error('Gagal sinkronisasi: '.$e->getMessage());
        report($e);

        return 1;
    }
})->purpose('Sinkronisasi data master Data Program (56 kolom) dari Google Spreadsheet');

Schedule::call(static function (): void {
    AutomationSchedule::recordSchedulerHeartbeat();
})
    ->name('scheduler-heartbeat')
    ->everyMinute();

// Sinkron Data Program dari Google Spreadsheet hanya bila dinyalakan (GOOGLE_SHEETS_ENABLED=true & ENABLE_AUTO_SYNC_SCHEDULE=true): sheet menimpa isi
// Data Program, termasuk tanggal & nomor yang sudah dikelola di aplikasi
if (config('services.data_program.auto_sync') && DataProgramSyncService::isGoogleSheetsEnabled()) {
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
// Batch dibatasi 5 data & maksimal 240 detik per putaran supaya penggunaan RAM tetap aman dan tidak menumpuk overlap lock.
if (! app()->environment('local') || env('ENABLE_AUTO_AI_SCHEDULE', false)) {
    Schedule::command('program:auto-analyze-ai --year=2026 --limit=5 --sleep=0.3 --max-seconds=240')
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
        ->withoutOverlapping(30)
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
// Stop mengambil job baru setelah 60 detik; job aktif (timeout 3600 detik) tetap diselesaikan sebelum lock 65 menit habis.
// Hanya spawn proses queue:work ketika ada job siap dijalankan agar tidak membuka proses PHP sia-sia tiap menit.
Schedule::command('queue:work --stop-when-empty --max-time=60 --tries=1 --timeout=3600 --memory=256')
    ->everyMinute()
    ->when(static function (): bool {
        try {
            if (! config('queue.scheduler_worker_enabled', true)) {
                return false;
            }

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
            $queue = (string) config("queue.connections.{$connection}.queue", 'default');
            $now = now()->timestamp;
            $retryAfter = (int) config("queue.connections.{$connection}.retry_after", 3700);

            return DB::connection($dbConnection)
                ->table($table)
                ->where('queue', $queue)
                ->where(static function (Builder $query) use ($now, $retryAfter): void {
                    $query->where(static function (Builder $available) use ($now): void {
                        $available->whereNull('reserved_at')->where('available_at', '<=', $now);
                    })->orWhere('reserved_at', '<=', $now - $retryAfter);
                })
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
Schedule::command('program:notify-telemarketing --limit=50')
    ->everyMinute()
    ->when(static fn (): bool => AutomationSchedule::isDueNow(AutomationSchedule::TELEMARKETING_WA))
    ->withoutOverlapping(15)
    ->runInBackground()
    ->onFailure(static function (): void {
        Log::warning('Scheduled command program:notify-telemarketing gagal dijalankan.');
    });

// WhatsApp pengingat ke telemarketing untuk invoice yang belum di-upload ke Form Program; jamnya diatur di Master → Jadwal Otomatis.
Schedule::command('invoices:remind-telemarketing-upload --limit=50')
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

// Bersihkan baris cache & cache_locks kadaluarsa tiap pukul 03:05 WIB agar tabel cache database tidak membengkak.
Schedule::call(static function (): void {
    try {
        if (config('cache.default') !== 'database') {
            return;
        }

        $connection = config('cache.stores.database.connection');
        $cacheTable = (string) config('cache.stores.database.table', 'cache');
        $lockTable = (string) (config('cache.stores.database.lock_table') ?: 'cache_locks');
        $now = now()->timestamp;

        DB::connection($connection)->table($cacheTable)->where('expiration', '<=', $now)->delete();
        DB::connection($connection)->table($lockTable)->where('expiration', '<=', $now)->delete();
    } catch (Throwable $e) {
        Log::warning("Gagal membersihkan cache kadaluarsa: {$e->getMessage()}");
    }
})
    ->name('prune-expired-database-cache')
    ->dailyAt('03:05');

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

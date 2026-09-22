<?php

namespace App\Console\Commands;

use App\Models\ProgramSubmission;
use App\Services\DocumentAnalysisService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AutoAnalyzeProgramAiCommand extends Command
{
    public const CACHE_KEY_STATUS = 'program_ai_running_status';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'program:auto-analyze-ai
                            {--year=2026 : Filter tahun data submission (default: 2026)}
                            {--limit=50 : Batas jumlah data yang diproses}
                            {--all : Proses seluruh data yang belum dianalisis tanpa batasan}
                            {--sleep=0.5 : Jeda waktu antar pemanggilan AI dalam detik}
                            {--force : Jalankan meskipun di lingkungan local}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan analisis AI di latar belakang untuk memeriksa kelengkapan dokumen CN, Agr, Faktur dan Status Potong Purchase';

    /**
     * Execute the console command.
     */
    public function handle(DocumentAnalysisService $aiService): int
    {
        if (app()->environment('local') && ! $this->option('force') && ! env('ENABLE_AUTO_AI_SCHEDULE', false)) {
            $this->warn('Auto-analyze AI dinonaktifkan di lingkungan local. Gunakan flag --force jika ingin tetap menjalankan.');

            return Command::SUCCESS;
        }

        $year = (string) $this->option('year');
        $limit = $this->option('all') ? 0 : (int) $this->option('limit');
        $sleepSeconds = (float) $this->option('sleep');

        $this->info('=== Auto-Analyze Dokumen Program AI di Latar Belakang ===');
        if ($year) {
            $this->line("Filter Tahun Submission: <comment>{$year}</comment>");
        }

        $query = ProgramSubmission::query();

        if ($year) {
            $query->where('submission_timestamp', 'like', "{$year}%");
        }

        // Filter: data yang belum dianalisis (cek_dokumen kosong atau status potong purchase kosong)
        $query->where(function ($q) {
            $q->whereNull('cek_dokumen')
                ->orWhere('cek_dokumen', '')
                ->orWhereNull('status_potong_purchase')
                ->orWhere('status_potong_purchase', '');
        });

        // Process latest unanalyzed submissions first (matching table view)
        $query->orderByDesc('id');

        $totalUnanalyzed = (clone $query)->count();
        $this->info("Ditemukan {$totalUnanalyzed} data yang belum dianalisis.");

        if ($totalUnanalyzed === 0) {
            $this->info('Semua data telah selesai dianalisis!');

            return Command::SUCCESS;
        }

        if ($limit > 0) {
            $query->limit($limit);
            $this->line("Memproses batch sebanyak <comment>{$limit}</comment> data...");
        } else {
            $this->line("Memproses seluruh <comment>{$totalUnanalyzed}</comment> data...");
        }

        $submissions = $query->get();
        $totalToProcess = $submissions->count();
        $startedAt = now()->toIso8601String();
        $cacheKey = self::CACHE_KEY_STATUS;

        Cache::put($cacheKey, [
            'is_running' => true,
            'started_at' => $startedAt,
            'total' => $totalToProcess,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'current_dealer' => null,
            'current_id' => null,
            'last_heartbeat' => time(),
        ], 300);

        $bar = $this->output->createProgressBar($totalToProcess);
        $bar->start();

        $successCount = 0;
        $errorCount = 0;
        $bisaPotongCount = 0;
        $belumBisaPotongCount = 0;

        try {
            foreach ($submissions as $sub) {
                $dealerIdentifier = $sub->dealer_name ?: ($sub->id_real ?: "ID #{$sub->id}");

                Cache::put($cacheKey, [
                    'is_running' => true,
                    'started_at' => $startedAt,
                    'total' => $totalToProcess,
                    'processed' => $successCount + $errorCount,
                    'success' => $successCount,
                    'failed' => $errorCount,
                    'current_dealer' => $dealerIdentifier,
                    'current_id' => $sub->id,
                    'last_heartbeat' => time(),
                ], 300);

                try {
                    $res = $aiService->analyzeSubmission($sub);
                    $successCount++;

                    if ($res['status_potong_purchase'] === 'BISA DI POTONG') {
                        $bisaPotongCount++;
                    } else {
                        $belumBisaPotongCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $this->newLine();
                    $this->error("ID {$sub->id} ({$sub->dealer_name}): ".$e->getMessage());
                }

                $bar->advance();

                Cache::put($cacheKey, [
                    'is_running' => true,
                    'started_at' => $startedAt,
                    'total' => $totalToProcess,
                    'processed' => $successCount + $errorCount,
                    'success' => $successCount,
                    'failed' => $errorCount,
                    'current_dealer' => $dealerIdentifier,
                    'current_id' => $sub->id,
                    'last_heartbeat' => time(),
                ], 300);

                if ($sleepSeconds > 0) {
                    usleep((int) ($sleepSeconds * 1000000));
                }
            }
        } finally {
            Cache::put($cacheKey, [
                'is_running' => false,
                'completed_at' => now()->toIso8601String(),
                'started_at' => $startedAt,
                'total' => $totalToProcess,
                'processed' => $successCount + $errorCount,
                'success' => $successCount,
                'failed' => $errorCount,
                'last_heartbeat' => time(),
            ], 45);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('=== Ringkasan Analisis AI ===');
        $this->line("- Total Diproses: <info>{$submissions->count()}</info>");
        $this->line("- Berhasil: <info>{$successCount}</info>");
        $this->line("  * BISA DI POTONG (Lengkap): <info>{$bisaPotongCount}</info>");
        $this->line("  * BELUM BISA POTONG (Kurang): <comment>{$belumBisaPotongCount}</comment>");
        if ($errorCount > 0) {
            $this->line("- Gagal: <error>{$errorCount}</error>");
        }

        $remaining = ProgramSubmission::where('submission_timestamp', 'like', "{$year}%")
            ->where(function ($q) {
                $q->whereNull('cek_dokumen')
                    ->orWhere('cek_dokumen', '')
                    ->orWhereNull('status_potong_purchase')
                    ->orWhere('status_potong_purchase', '');
            })->count();

        $this->line("- Sisa Data Tahun {$year} yang Belum Dicek: <comment>{$remaining}</comment>");

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\ProgramSubmission;
use App\Services\DocumentAnalysisService;
use Exception;
use Illuminate\Console\Command;

class AutoAnalyzeProgramAiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'program:auto-analyze-ai
                            {--year=2026 : Filter tahun data submission (default: 2026)}
                            {--limit=50 : Batas jumlah data yang diproses}
                            {--all : Proses seluruh data yang belum dianalisis tanpa batasan}
                            {--sleep=0.5 : Jeda waktu antar pemanggilan AI dalam detik}';

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

        // Urutkan prioritas: yang memiliki link dokumen lebih lengkap dianalisis lebih dahulu
        $query->orderByRaw("
            (CASE WHEN credit_note_url != '' AND credit_note_url IS NOT NULL THEN 1 ELSE 0 END
             + CASE WHEN agreement_url != '' AND agreement_url IS NOT NULL THEN 1 ELSE 0 END
             + CASE WHEN tax_invoice_url != '' AND tax_invoice_url IS NOT NULL THEN 1 ELSE 0 END) DESC, id DESC
        ");

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
        $bar = $this->output->createProgressBar($submissions->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;
        $bisaPotongCount = 0;
        $belumBisaPotongCount = 0;

        foreach ($submissions as $sub) {
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

            if ($sleepSeconds > 0) {
                usleep((int) ($sleepSeconds * 1000000));
            }
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

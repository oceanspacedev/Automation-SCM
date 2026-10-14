<?php

namespace App\Console\Commands;

use App\Services\DealerImportService;
use Illuminate\Console\Command;

class ImportDealers extends Command
{
    /**
     * @var string
     */
    protected $signature = 'dealers:import
                            {file : Path file Excel master dealer (sheet Master Dealer CV TOP)}
                            {--keep-existing : Jangan hapus dealer lama, hanya tambah/perbarui}';

    /**
     * @var string
     */
    protected $description = 'Import Master Dealer dari file master CV TOP (kode BT sebagai kunci)';

    /**
     * Execute the console command.
     */
    public function handle(DealerImportService $importer): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $replace = ! $this->option('keep-existing');
        $result = $importer->import($file, $replace);

        $this->info(($replace ? 'Master Dealer diganti: ' : 'Master Dealer diperbarui: ').$result['imported'].' dealer diimport.');
        foreach ($result['per_area'] as $area => $count) {
            $this->line("  - {$area}: {$count}");
        }
        $this->line("Kode BT 'BTO…' diubah jadi 'BT0…': {$result['normalized_kode_bt']}");
        foreach ($result['kept_original_kode_bt'] as $note) {
            $this->warn("Kode BT tetap ditulis asli: {$note}");
        }

        if ($result['duplicates']) {
            $this->warn('Kode BT dobel di file (baris terakhir yang dipakai): '.collect($result['duplicates'])->map(fn ($count, $kode) => "{$kode} ({$count}x)")->implode(', '));
        }

        if ($result['regions_created']) {
            $this->line('Region baru di Master Region: '.implode(', ', $result['regions_created']));
        }

        if ($result['skipped']) {
            $path = storage_path('app/dealer-import/dealer-belum-diimport.xlsx');
            $importer->writeSkippedRows($result['skipped'], $result['header'], $path);
            $this->warn(count($result['skipped'])." baris dilewati, daftarnya: {$path}");
        }

        return self::SUCCESS;
    }
}

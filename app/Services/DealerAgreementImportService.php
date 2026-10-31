<?php

namespace App\Services;

use App\Models\Dealer;
use Closure;
use Exception;
use Illuminate\Support\Facades\Concurrency;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Fills Master Dealer agreements from a Data Program spreadsheet (e.g. DRAFT TEMPLATE PROGRAM, sheet RAW JABAR).
 * The sheet has one agreement link per program row, oldest rows first. Each store gets its newest link that the AI
 * reads as an acceptable agreement (see DocumentAnalysisService::isAcceptableAgreement); when none is, an agreement
 * from the file is removed. An agreement that is not in the file (e.g. sent later through the Form Program) is kept.
 */
class DealerAgreementImportService
{
    public function __construct(private DocumentAnalysisService $analysis) {}

    /**
     * The first sheet whose header row has the Kode BT and AGREMENT columns, or null when the file has none.
     */
    public function findSheet(string $path): ?string
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter($this->readFilter(fn (string $column, int $row) => $row === 1));

        foreach ($reader->load($path)->getAllSheets() as $worksheet) {
            if ($this->agreementColumns($worksheet->toArray(null, false, false, true)[1] ?? []) !== null) {
                return $worksheet->getTitle();
            }
        }

        return null;
    }

    /**
     * @param  (Closure(int $done, int $total, int $checked): void)|null  $onProgress  called as documents are checked: stores decided, stores in total, documents checked
     * @return array{filled: int, replaced: int, same: int, removed: int, none: int, kept: int, unchecked: int, not_found: int}
     *
     * @throws Exception when the sheet or its Kode BT & AGREMENT columns are missing
     */
    public function import(string $path, string $sheet, int $parallel = 8, ?Closure $onProgress = null): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (! in_array($sheet, $sheets = $reader->listWorksheetNames($path), true)) {
            throw new Exception("Sheet \"{$sheet}\" tidak ditemukan. Sheet yang ada: ".implode(', ', $sheets));
        }
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheet]);

        $reader->setReadFilter($this->readFilter(fn (string $column, int $row) => $row === 1));
        $columns = $this->agreementColumns($reader->load($path)->getSheetByName($sheet)->toArray(null, false, false, true)[1] ?? []);
        if ($columns === null) {
            throw new Exception("Kolom \"Kode BT\" dan \"AGREMENT\" tidak ditemukan di baris pertama sheet \"{$sheet}\".");
        }
        [$kodeBtColumn, $agreementColumn] = $columns;

        $reader->setReadFilter($this->readFilter(fn (string $column) => $column === $kodeBtColumn || $column === $agreementColumn));
        $worksheet = $reader->load($path)->getSheetByName($sheet);
        $lastRow = $worksheet->getHighestDataRow();
        $kodeBts = $worksheet->rangeToArray("{$kodeBtColumn}2:{$kodeBtColumn}{$lastRow}", null, false, false);
        $agreements = $worksheet->rangeToArray("{$agreementColumn}2:{$agreementColumn}{$lastRow}", null, false, false);

        // Link agreement tiap kode BT, urut dari yang lama ke yang terbaru
        $links = [];
        foreach ($kodeBts as $index => [$kodeBt]) {
            $kodeBt = strtoupper(preg_replace('/\s+/', '', (string) $kodeBt));
            $url = trim((string) $agreements[$index][0]);
            if (preg_match('/^BTO?\d{3,6}$/', $kodeBt) && preg_match('#^https?://#i', $url)) {
                unset($links[$kodeBt][$url]);
                $links[$kodeBt][$url] = true;
            }
        }

        $counts = ['filled' => 0, 'replaced' => 0, 'same' => 0, 'removed' => 0, 'none' => 0, 'kept' => 0, 'unchecked' => 0, 'not_found' => 0];
        $dealers = [];
        $candidates = [];
        foreach ($links as $kodeBt => $storeLinks) {
            $dealer = Dealer::withoutGlobalScope('area')->where('kode_bt', $kodeBt)->first()
                ?? Dealer::withoutGlobalScope('area')->where('kode_bt', Dealer::normalizeKodeBt($kodeBt))->first();

            if (! $dealer) {
                $counts['not_found']++;

                continue;
            }

            // Agreement yang tidak ada di file (mis. dikirim lewat Form Program) tidak diubah
            $fileIds = array_map($this->driveFileId(...), array_keys($storeLinks));
            if ($dealer->agreement_url && ! in_array($this->driveFileId($dealer->agreement_url), $fileIds, true)) {
                $counts['kept']++;

                continue;
            }

            $dealers[$kodeBt] = $dealer;
            $candidates[$kodeBt] = array_reverse(array_keys($storeLinks));
        }

        $storeNames = array_map(fn (Dealer $dealer) => (string) $dealer->dealer_name, $dealers);
        foreach ($this->newestAcceptableAgreements($candidates, $storeNames, max(1, $parallel), $onProgress) as $kodeBt => $accepted) {
            $dealer = $dealers[$kodeBt];

            if ($accepted === false) {
                $counts['unchecked']++;

                continue;
            }

            if ($accepted === null) {
                $counts[$dealer->agreement_url ? 'removed' : 'none']++;
                if ($dealer->agreement_url) {
                    $dealer->forceFill(['agreement_url' => null, 'agreement_updated_at' => null])->save();
                }

                continue;
            }

            $current = $dealer->agreement_url ? $this->driveFileId($dealer->agreement_url) : null;
            $outcome = match ($current) {
                null => 'filled',
                $this->driveFileId($accepted) => 'same',
                default => 'replaced',
            };
            $counts[$outcome]++;

            if ($outcome !== 'same') {
                $dealer->forceFill(['agreement_url' => $accepted, 'agreement_updated_at' => now()])->save();
            }
        }

        return $counts;
    }

    /**
     * Each store's newest acceptable agreement: links are read from the newest, several at a time in parallel,
     * until one is acceptable.
     *
     * @param  array<string, list<string>>  $candidates  kode BT => links, newest first
     * @param  array<string, string>  $storeNames  kode BT => store name in Master Dealer
     * @param  (Closure(int $done, int $total, int $checked): void)|null  $onProgress
     * @return array<string, string|false|null> kode BT => accepted link, null when no link is acceptable, false when the AI could not read one
     */
    private function newestAcceptableAgreements(array $candidates, array $storeNames, int $parallel, ?Closure $onProgress): array
    {
        $total = count($candidates);
        $accepted = [];
        $checked = 0;

        for ($round = 0; $candidates !== []; $round++) {
            $urls = array_map(fn (array $links) => $links[$round] ?? null, $candidates);

            // Link toko ini sudah habis dicoba: tidak ada agreement yang memenuhi
            foreach (array_keys(array_filter($urls, fn (?string $url) => $url === null)) as $kodeBt) {
                $accepted[$kodeBt] = null;
                unset($candidates[$kodeBt]);
            }

            // Toko diputuskan per kelompok dokumen, supaya progresnya bergerak sepanjang putaran
            foreach (array_chunk(array_values(array_unique(array_filter($urls))), $parallel) as $chunk) {
                // Satu closure per baris: closure dikirim ke proses lain lewat kodenya, dan dua closure di satu baris tertukar.
                // Hasilnya dikirim balik sebagai base64, karena teks berisi "..." (mis. nama di cap) rusak di perjalanan.
                $tasks = [];
                foreach ($chunk as $url) {
                    $tasks[] = static fn () => base64_encode(json_encode(app(DocumentAnalysisService::class)->inspectAgreement($url)));
                }
                $results = Concurrency::run($tasks, 300);
                $inspections = [];
                foreach ($chunk as $index => $url) {
                    $inspections[$url] = json_decode(base64_decode($results[$index]), true);
                }
                $checked += count($chunk);

                foreach (array_intersect($urls, $chunk) as $kodeBt => $url) {
                    $inspection = $inspections[$url];

                    if ($inspection !== null && ! $this->analysis->isAcceptableAgreement($inspection, $storeNames[$kodeBt])) {
                        continue; // coba link yang lebih lama di putaran berikutnya
                    }

                    $accepted[$kodeBt] = $inspection === null ? false : $url;
                    unset($candidates[$kodeBt]);
                }

                if ($onProgress) {
                    $onProgress(count($accepted), $total, $checked);
                }
            }
        }

        if ($onProgress) {
            $onProgress(count($accepted), $total, $checked);
        }

        return $accepted;
    }

    /**
     * The column letters of Kode BT and AGREMENT in a header row, or null when either is missing.
     *
     * @param  array<string, mixed>  $header  column letter => title
     * @return array{0: string, 1: string}|null
     */
    private function agreementColumns(array $header): ?array
    {
        $header = array_map(fn ($title) => strtoupper(trim((string) $title)), $header);
        $kodeBtColumn = array_search('KODE BT', $header, true);
        $agreementColumn = array_search('AGREMENT', $header, true) ?: array_search('AGREEMENT', $header, true);

        return $kodeBtColumn !== false && $agreementColumn !== false ? [$kodeBtColumn, $agreementColumn] : null;
    }

    /**
     * @param  callable(string, int): bool  $accepts
     */
    private function readFilter(callable $accepts): IReadFilter
    {
        return new class($accepts) implements IReadFilter
        {
            /**
             * @param  callable(string, int): bool  $accepts
             */
            public function __construct(private $accepts) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return ($this->accepts)($columnAddress, $row);
            }
        };
    }

    /**
     * The Google Drive file id of a link, so "open?id=…" and "file/d/…/view" links of one file compare equal.
     */
    private function driveFileId(string $url): string
    {
        return preg_match('#[?&]id=([\w-]+)|/d/([\w-]+)#', $url, $match) ? ($match[1] ?: $match[2]) : trim($url);
    }
}

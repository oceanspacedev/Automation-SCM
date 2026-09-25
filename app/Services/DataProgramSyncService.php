<?php

namespace App\Services;

use App\Models\DataProgram;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataProgramSyncService
{
    public const CACHE_KEY_SPREADSHEET_URL = 'data_program_spreadsheet_url';

    public const DEFAULT_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/1w8J_ahdfk-Dj0NZWkXf1GJucpusQ4vQaerdU5wfnkJc/export?format=csv&gid=1715579975';

    /**
     * Get configured Google Sheet CSV URL.
     */
    public function getSpreadsheetUrl(): string
    {
        $url = (string) Cache::get(self::CACHE_KEY_SPREADSHEET_URL, env('DATA_PROGRAM_SPREADSHEET_URL', ''));

        if (! empty($url)) {
            return $url;
        }

        return self::DEFAULT_SPREADSHEET_URL;
    }

    public const CACHE_KEY_WEBAPP_URL = 'data_program_webapp_url';

    /**
     * Get configured Google Apps Script Web App URL for pushing updates.
     */
    public function getWebAppUrl(): string
    {
        $url = (string) Cache::get(self::CACHE_KEY_WEBAPP_URL, env('DATA_PROGRAM_WEBAPP_URL', ''));
        if (! empty($url) && str_contains($url, 'script.google.com/macros/s/')) {
            return $url;
        }

        $sheetUrl = (string) Cache::get(self::CACHE_KEY_SPREADSHEET_URL, env('DATA_PROGRAM_SPREADSHEET_URL', ''));
        if (! empty($sheetUrl) && str_contains($sheetUrl, 'script.google.com/macros/s/')) {
            return $sheetUrl;
        }

        $formWebappUrl = (string) Cache::get(ProgramSubmissionService::CACHE_KEY_WEBAPP_URL, env('GOOGLE_SHEET_WEBAPP_URL', ''));
        if (! empty($formWebappUrl) && str_contains($formWebappUrl, 'script.google.com/macros/s/')) {
            return $formWebappUrl;
        }

        return ProgramSubmissionService::DEFAULT_WEBAPP_URL;
    }

    /**
     * Set configured Google Apps Script Web App URL.
     */
    public function setWebAppUrl(string $url): void
    {
        Cache::forever(self::CACHE_KEY_WEBAPP_URL, trim($url));
    }

    /**
     * Set configured Google Sheet CSV URL.
     */
    public function setSpreadsheetUrl(string $url): void
    {
        Cache::forever(self::CACHE_KEY_SPREADSHEET_URL, trim($url));
    }

    /**
     * Parse numeric amounts from Indonesian formatted strings (e.g. "  200.000 " or "180.180,50").
     */
    public function parseAmount(mixed $val): float
    {
        if ($val === null) {
            return 0.0;
        }

        $clean = trim(str_replace(['Rp', ' ', "\xc2\xa0"], '', (string) $val));

        if ($clean === '' || $clean === '-' || str_starts_with($clean, '#')) {
            return 0.0;
        }

        // Handle negative numbers like (200.000)
        $isNegative = str_starts_with($clean, '(') && str_ends_with($clean, ')');
        if ($isNegative) {
            $clean = trim($clean, '()');
        }

        // Format 200.000 or 1.234.567 or 180.180,50
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $clean)) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (preg_match('/^\d+(,\d+)?$/', $clean)) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = preg_replace('/[^\d.-]/', '', $clean);
        }

        $num = (float) $clean;

        return $isNegative ? -$num : $num;
    }

    /**
     * Sync data from Google Spreadsheet (either Apps Script Web App JSON or CSV stream).
     *
     * @return array{total_rows: int, synced_count: int, new_count: int, updated_count: int, message: string}
     */
    public function sync(?string $customUrl = null, int $limit = 0): array
    {
        $url = trim((string) ($customUrl ?: $this->getSpreadsheetUrl()));

        if (empty($url)) {
            throw new Exception('URL Google Spreadsheet belum diatur.');
        }

        if (str_contains($url, 'script.google.com/macros/s/')) {
            return $this->syncFromWebAppUrl($url, $limit);
        }

        return $this->syncFromCsvUrl($url, $limit);
    }

    /**
     * Sync data from Google Apps Script Web App JSON endpoint.
     *
     * @return array{total_rows: int, synced_count: int, new_count: int, updated_count: int, message: string}
     */
    public function syncFromWebAppUrl(string $url, int $limit = 0): array
    {
        try {
            $fetchUrl = $url;
            if ($limit > 0) {
                $separator = str_contains($fetchUrl, '?') ? '&' : '?';
                $fetchUrl .= "{$separator}limit={$limit}";
            }

            $response = Http::withoutVerifying()
                ->withOptions(['allow_redirects' => true])
                ->timeout(15)
                ->get($fetchUrl);

            if ($response->successful()) {
                $body = trim($response->body());

                if (! str_contains($body, 'accounts.google.com') && (! str_contains($body, '<!DOCTYPE html>') || ! str_contains($body, 'ServiceLogin'))) {
                    $json = $response->json();
                    $rows = [];

                    if (is_array($json) && isset($json['rows']) && is_array($json['rows'])) {
                        $rows = $json['rows'];
                    } elseif (is_array($json) && isset($json[0]) && is_array($json[0])) {
                        $rows = $json;
                    }

                    if (! empty($rows) && count($rows) >= 2) {
                        $dataRows = array_slice($rows, 1);
                        if ($limit > 0 && count($dataRows) > $limit) {
                            $dataRows = array_slice($dataRows, 0, $limit);
                        }

                        $payload = [
                            'start_row' => 2,
                            'rows' => $dataRows,
                        ];

                        $result = $this->saveWebhookPayload($payload);

                        return [
                            'total_rows' => count($dataRows),
                            'synced_count' => $result['saved_count'],
                            'new_count' => $result['saved_count'],
                            'updated_count' => 0,
                            'message' => "Sinkronisasi berhasil ({$result['saved_count']} baris data diproses dari Google Apps Script).",
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Sinkronisasi Apps Script doGet tidak merespon/timeout ({$e->getMessage()}), beralih otomatis ke CSV export cepat.");
        }

        // Otomatis fallback ke Google Sheet CSV export stream (jauh lebih cepat untuk 21.000+ baris data)
        return $this->syncFromCsvUrl(self::DEFAULT_SPREADSHEET_URL, $limit);
    }

    /**
     * Push updated rows back to Google Spreadsheet via Apps Script Web App (doPost).
     *
     * @param array<int, array{
     *     kode_bt?: ?string,
     *     dealer_name: string,
     *     program_name?: ?string,
     *     status_potong_purchase?: ?string,
     *     cek_dokumen?: ?string,
     *     keterangan?: ?string,
     *     cn?: ?string,
     *     agrement?: ?string,
     *     cek_fp?: ?string,
     *     noted?: ?string
     * }> $rowsToUpdate
     * @return array{success: bool, updated_count: int, message: string}
     */
    public function pushUpdatesToSpreadsheet(array $rowsToUpdate, ?string $customUrl = null): array
    {
        $url = trim((string) ($customUrl ?: $this->getWebAppUrl()));

        if (empty($url) || ! str_contains($url, 'script.google.com/macros/s/')) {
            return [
                'success' => false,
                'updated_count' => 0,
                'message' => 'URL Google Apps Script Web App belum diatur atau bukan Web App Apps Script.',
            ];
        }

        if (empty($rowsToUpdate)) {
            return [
                'success' => true,
                'updated_count' => 0,
                'message' => 'Tidak ada baris data yang perlu diupdate ke spreadsheet.',
            ];
        }

        try {
            $payload = [
                'action' => 'update_rows',
                'rows' => $rowsToUpdate,
            ];

            Log::info('Mengirim '.count($rowsToUpdate).' baris update ke Google Spreadsheet: '.$url);

            $response = Http::withoutVerifying()
                ->withOptions(['allow_redirects' => true])
                ->timeout(120)
                ->post($url, $payload);

            Log::info("Respon dari Google Apps Script (HTTP {$response->status()}): ".substr($response->body(), 0, 500));

            if (! $response->successful()) {
                throw new Exception("HTTP {$response->status()}: ".$response->body());
            }

            $json = $response->json();
            $updatedCount = (int) ($json['updated_count'] ?? count($rowsToUpdate));

            return [
                'success' => true,
                'updated_count' => $updatedCount,
                'message' => "Berhasil memperbarui {$updatedCount} baris di Google Spreadsheet.",
            ];
        } catch (\Throwable $e) {
            Log::error('Gagal update ke Google Spreadsheet: '.$e->getMessage());

            return [
                'success' => false,
                'updated_count' => 0,
                'message' => 'Gagal memperbarui Google Spreadsheet: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Sync data from Google Spreadsheet CSV stream.
     *
     * @return array{total_rows: int, synced_count: int, new_count: int, updated_count: int, message: string}
     */
    public function syncFromCsvUrl(string $url, int $limit = 0): array
    {
        // Convert standard edit/view link to CSV export URL if needed
        if (str_contains($url, 'docs.google.com/spreadsheets/d/')) {
            if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $m)) {
                $sheetId = $m[1];
                $gid = '0';
                if (preg_match('/[#&?]gid=([0-9]+)/', $url, $gm)) {
                    $gid = $gm[1];
                }
                $url = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
            }
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'dp_csv_');
        $fp = fopen($tmpFile, 'w+');

        // Tambahkan query parameter cache-buster agar CDN Google tidak menyajikan cache CSV usang
        $fetchUrl = $url;
        $separator = str_contains($fetchUrl, '?') ? '&' : '?';
        $fetchUrl .= "{$separator}_t=".time();

        $ch = curl_init($fetchUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cache-Control: no-cache, no-store, must-revalidate',
            'Pragma: no-cache',
            'Expires: 0',
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (! $success || $httpCode !== 200 || filesize($tmpFile) < 100) {
            @unlink($tmpFile);
            throw new Exception("Gagal mengunduh spreadsheet dari Google (HTTP {$httpCode}). Pastikan opsi 'Share / Siapa saja yang memiliki link' telah aktif.");
        }

        $handle = fopen($tmpFile, 'r');
        if (! $handle) {
            @unlink($tmpFile);
            throw new Exception('Gagal membaca temporary file CSV.');
        }

        // Read header
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (! $header || count($header) < 5) {
            fclose($handle);
            @unlink($tmpFile);
            throw new Exception('Format kolom spreadsheet tidak valid atau kosong.');
        }

        $batch = [];
        $batchSize = 400;
        $totalProcessed = 0;
        $rowIdx = 1; // 1 was header

        $syncTimestamp = now()->subSecond()->toDateTimeString();
        $now = now()->toDateTimeString();

        try {
            DB::beginTransaction();

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rowIdx++;

                $mapped = $this->mapRowToData($row, $rowIdx, $now);
                if (! $mapped) {
                    continue;
                }

                $batch[] = $mapped;
                $totalProcessed++;

                if (count($batch) >= $batchSize) {
                    $this->upsertBatch($batch);
                    $batch = [];
                }

                if ($limit > 0 && $totalProcessed >= $limit) {
                    break;
                }
            }

            if (! empty($batch)) {
                $this->upsertBatch($batch);
            }

            // Hapus data di database yang barisnya sudah dihapus dari Spreadsheet (termasuk duplikat hash lama)
            if ($limit === 0 && $totalProcessed > 0) {
                DataProgram::where('updated_at', '<', $syncTimestamp)->delete();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            @unlink($tmpFile);
            Log::error('DataProgram sync error: '.$e->getMessage());
            throw $e;
        }

        fclose($handle);
        @unlink($tmpFile);

        return [
            'total_rows' => $totalProcessed,
            'synced_count' => $totalProcessed,
            'new_count' => $totalProcessed,
            'updated_count' => 0,
            'message' => "Sinkronisasi berhasil ({$totalProcessed} baris data diproses dari Google Spreadsheet).",
        ];
    }

    /**
     * Map a 56-column row array to DataProgram model attributes.
     */
    public function mapRowToData(array $row, int $rowIdx, ?string $now = null): ?array
    {
        // Skip completely empty lines
        if (empty(array_filter($row, fn ($v) => ! is_null($v) && trim((string) $v) !== ''))) {
            return null;
        }

        $dealerName = trim((string) ($row[0] ?? ''));
        $program = trim((string) ($row[1] ?? ''));
        $kodeBt = trim((string) ($row[2] ?? ''));
        $programName = trim((string) ($row[3] ?? ''));

        if (empty($dealerName) && empty($program) && empty($kodeBt) && empty($programName)) {
            return null;
        }

        // Identifier konsisten berdasarkan nomor baris spreadsheet agar saat data diubah tidak membuat baris duplikat baru
        $rowHash = sprintf('row_%d', $rowIdx);
        $now = $now ?? now()->toDateTimeString();

        return [
            'row_hash' => $rowHash,
            'dealer_name' => $dealerName ?: null,
            'program' => $program ?: null,
            'kode_bt' => $kodeBt ?: null,
            'program_name' => $programName ?: null,
            'periode' => trim((string) ($row[4] ?? '')) ?: null,
            'region' => trim((string) ($row[5] ?? '')) ?: null,
            'no_po' => trim((string) ($row[6] ?? '')) ?: null,
            'id_gs' => trim((string) ($row[7] ?? '')) ?: null,
            'kode_supplier' => trim((string) ($row[8] ?? '')) ?: null,
            'status_dl' => trim((string) ($row[9] ?? '')) ?: null,
            'sales_person' => trim((string) ($row[10] ?? '')) ?: null,
            'telemarketing' => trim((string) ($row[11] ?? '')) ?: null,
            'wajib_pajak' => trim((string) ($row[12] ?? '')) ?: null,
            'trf_pph' => trim((string) ($row[13] ?? '')) ?: null,
            'incentive' => $this->parseAmount($row[14] ?? 0),
            'dpp' => $this->parseAmount($row[15] ?? 0),
            'dpp_lain' => $this->parseAmount($row[16] ?? 0),
            'ppn' => $this->parseAmount($row[17] ?? 0),
            'nilai_pph' => $this->parseAmount($row[18] ?? 0),
            'net_pay' => $this->parseAmount($row[19] ?? 0),
            'cek_pajak_tarif' => trim((string) ($row[20] ?? '')) ?: null,
            'selisih' => $this->parseAmount($row[21] ?? 0),
            'note_pph' => trim((string) ($row[22] ?? '')) ?: null,
            'no_faktur_pajak' => trim((string) ($row[23] ?? '')) ?: null,
            'ket_faktur_pajak' => trim((string) ($row[24] ?? '')) ?: null,
            'no_po_sj' => trim((string) ($row[25] ?? '')) ?: null,
            'no_transaksi' => trim((string) ($row[26] ?? '')) ?: null,
            'tgl_input' => trim((string) ($row[27] ?? '')) ?: null,
            'tgl_share_cn' => trim((string) ($row[28] ?? '')) ?: null,
            'lama_pending' => trim((string) ($row[29] ?? '')) ?: null,
            'keterangan' => trim((string) ($row[30] ?? '')) ?: null,
            'cek_dokumen' => trim((string) ($row[31] ?? '')) ?: null,
            'status_potong_purchase' => trim((string) ($row[32] ?? '')) ?: null,
            'status_potong_ar' => trim((string) ($row[33] ?? '')) ?: null,
            'tgl_potong_tf' => trim((string) ($row[34] ?? '')) ?: null,
            'no_uid' => trim((string) ($row[35] ?? '')) ?: null,
            'no_pembayaran' => trim((string) ($row[36] ?? '')) ?: null,
            'tgl_input_bank_pph' => trim((string) ($row[37] ?? '')) ?: null,
            'tf_status' => trim((string) ($row[38] ?? '')) ?: null,
            'tgl_proses' => trim((string) ($row[39] ?? '')) ?: null,
            'tgl_sj' => trim((string) ($row[40] ?? '')) ?: null,
            'no_sj' => trim((string) ($row[41] ?? '')) ?: null,
            'info_bank' => trim((string) ($row[42] ?? '')) ?: null,
            'pending_potongan' => trim((string) ($row[43] ?? '')) ?: null,
            'npwp' => trim((string) ($row[44] ?? '')) ?: null,
            'nama_npwp' => trim((string) ($row[45] ?? '')) ?: null,
            'program_2' => trim((string) ($row[46] ?? '')) ?: null,
            'cn' => trim((string) ($row[47] ?? '')) ?: null,
            'agrement' => trim((string) ($row[48] ?? '')) ?: null,
            'cek_fp' => trim((string) ($row[49] ?? '')) ?: null,
            'cek_evidance' => trim((string) ($row[50] ?? '')) ?: null,
            'noted' => trim((string) ($row[51] ?? '')) ?: null,
            'norek' => trim((string) ($row[52] ?? '')) ?: null,
            'namrek' => trim((string) ($row[53] ?? '')) ?: null,
            'bank' => trim((string) ($row[54] ?? '')) ?: null,
            'big_region' => trim((string) ($row[55] ?? '')) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Process incoming webhook payload from Google Apps Script.
     * Supports single row (values/row + row_index) or multiple rows (rows).
     *
     * @return array{saved_count: int, message: string}
     */
    public function saveWebhookPayload(array $payload): array
    {
        $batch = [];
        $now = now()->toDateTimeString();

        if (isset($payload['values']) && is_array($payload['values'])) {
            $rowIdx = (int) ($payload['row_index'] ?? ($payload['rowNumber'] ?? 2));
            $mapped = $this->mapRowToData($payload['values'], $rowIdx, $now);
            if ($mapped) {
                $batch[] = $mapped;
            }
        } elseif (isset($payload['row']) && is_array($payload['row'])) {
            $rowIdx = (int) ($payload['row_index'] ?? ($payload['rowNumber'] ?? 2));
            $mapped = $this->mapRowToData($payload['row'], $rowIdx, $now);
            if ($mapped) {
                $batch[] = $mapped;
            }
        } elseif (isset($payload['rows']) && is_array($payload['rows'])) {
            $startRow = (int) ($payload['start_row'] ?? ($payload['startRow'] ?? 2));
            foreach ($payload['rows'] as $i => $row) {
                if (is_array($row)) {
                    $mapped = $this->mapRowToData($row, $startRow + $i, $now);
                    if ($mapped) {
                        $batch[] = $mapped;
                    }
                }
            }
        } elseif (array_is_list($payload) && ! empty($payload) && is_array($payload[0])) {
            foreach ($payload as $i => $row) {
                if (is_array($row)) {
                    $mapped = $this->mapRowToData($row, 2 + $i, $now);
                    if ($mapped) {
                        $batch[] = $mapped;
                    }
                }
            }
        }

        if (empty($batch)) {
            return [
                'saved_count' => 0,
                'message' => 'Tidak ada baris data valid yang diproses dari webhook.',
            ];
        }

        foreach (array_chunk($batch, 300) as $chunk) {
            $this->upsertBatch($chunk);
        }

        return [
            'saved_count' => count($batch),
            'message' => sprintf('Berhasil menyimpan %d baris Data Program.', count($batch)),
        ];
    }

    /**
     * Upsert batch using unique row_hash.
     */
    protected function upsertBatch(array $batch): void
    {
        $updateColumns = [
            'dealer_name', 'program', 'kode_bt', 'program_name', 'periode',
            'region', 'no_po', 'id_gs', 'kode_supplier', 'status_dl',
            'sales_person', 'telemarketing', 'wajib_pajak', 'trf_pph',
            'incentive', 'dpp', 'dpp_lain', 'ppn', 'nilai_pph', 'net_pay',
            'cek_pajak_tarif', 'selisih', 'note_pph', 'no_faktur_pajak',
            'ket_faktur_pajak', 'no_po_sj', 'no_transaksi', 'tgl_input',
            'tgl_share_cn', 'lama_pending', 'keterangan', 'cek_dokumen',
            'status_potong_purchase', 'status_potong_ar', 'tgl_potong_tf',
            'no_uid', 'no_pembayaran', 'tgl_input_bank_pph', 'tf_status',
            'tgl_proses', 'tgl_sj', 'no_sj', 'info_bank', 'pending_potongan',
            'npwp', 'nama_npwp', 'program_2', 'cn', 'agrement', 'cek_fp',
            'cek_evidance', 'noted', 'norek', 'namrek', 'bank', 'big_region',
            'updated_at',
        ];

        DataProgram::upsert($batch, ['row_hash'], $updateColumns);
    }
}

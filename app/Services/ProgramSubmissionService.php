<?php

namespace App\Services;

use App\Models\ProgramSubmission;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProgramSubmissionService
{
    public const CACHE_KEY_WEBAPP_URL = 'google_sheet_webapp_url';

    public const DEFAULT_WEBAPP_URL = 'https://script.google.com/macros/s/AKfycbxzjxTieMwGCiviO05imy29rgiWzeDvgW8Pq6hmzzqPEduWCiVrCn-7G5gyCn1n4-c3sQ/exec';

    /**
     * Get configured Google Apps Script Web App URL.
     */
    public function getWebAppUrl(): string
    {
        $url = (string) Cache::get(self::CACHE_KEY_WEBAPP_URL, env('GOOGLE_SHEET_WEBAPP_URL', ''));

        if (! empty($url)) {
            return $url;
        }

        return self::DEFAULT_WEBAPP_URL;
    }

    /**
     * Save configured Google Apps Script Web App URL.
     */
    public function setWebAppUrl(string $url): void
    {
        Cache::forever(self::CACHE_KEY_WEBAPP_URL, trim($url));
    }

    /**
     * Sync submissions from Google Apps Script Web App.
     */
    public function syncFromWebAppUrl(?string $customUrl = null, int $limit = 0): array
    {
        $url = trim((string) ($customUrl ?: $this->getWebAppUrl()));

        if (empty($url)) {
            throw new Exception('URL Google Apps Script Web App belum diatur. Silakan atur URL Web App terlebih dahulu.');
        }

        if ($limit > 0) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= "{$separator}limit={$limit}";
        }

        $response = Http::withoutVerifying()
            ->withOptions(['allow_redirects' => true])
            ->timeout(60)
            ->get($url);

        if (! $response->successful()) {
            throw new Exception("Gagal menghubungi Google Apps Script (HTTP {$response->status()}). Pastikan Deployment Web App sudah 'Anyone' dan URL tepat.");
        }

        $json = $response->json();
        $rows = [];

        if (is_array($json) && isset($json['rows']) && is_array($json['rows'])) {
            $rows = $json['rows'];
        } elseif (is_array($json) && isset($json[0]) && is_array($json[0])) {
            $rows = $json;
        } else {
            // Check if response is plain text CSV
            $body = trim($response->body());
            if (! empty($body) && str_contains($body, ',')) {
                $lines = explode("\n", $body);
                foreach ($lines as $line) {
                    if (trim($line) !== '') {
                        $rows[] = str_getcsv($line);
                    }
                }
            }
        }

        if (empty($rows) || count($rows) < 2) {
            return [
                'total_rows' => 0,
                'synced_count' => 0,
                'new_count' => 0,
                'updated_count' => 0,
                'message' => 'Tidak ada baris data yang ditemukan di spreadsheet.',
            ];
        }

        return $this->processSheetRows($rows);
    }

    /**
     * Process 2D array of rows from Google Sheet (Row 0 is Header).
     */
    public function processSheetRows(array $rows): array
    {
        $headerRow = array_map(fn ($col) => strtolower(trim((string) $col)), $rows[0]);
        $dataRows = array_slice($rows, 1);

        // Find column indices
        $colMap = [
            'timestamp' => $this->findHeaderIndex($headerRow, ['timestamp', 'waktu', 'tanggal']),
            'region' => $this->findHeaderIndex($headerRow, ['region', 'wilayah']),
            'id_real' => $this->findHeaderIndex($headerRow, ['id real', 'id_real', 'id realme', 'id']),
            'dealer_name' => $this->findHeaderIndex($headerRow, ['nama dealer', 'dealer', 'toko']),
            'program_name' => $this->findHeaderIndex($headerRow, ['nama program', 'program']),
            'sales_name' => $this->findHeaderIndex($headerRow, ['nama sales', 'sales']),
            'credit_note' => $this->findHeaderIndex($headerRow, ['credit note', 'dokumen credit', 'cn', 'dokumen credit note']),
            'agreement' => $this->findHeaderIndex($headerRow, ['agreement', 'perjanjian']),
            'tax_invoice' => $this->findHeaderIndex($headerRow, ['faktur pajak', 'faktur', 'tax invoice']),
        ];

        $totalRows = count($dataRows);
        $newCount = 0;
        $updatedCount = 0;

        foreach ($dataRows as $row) {
            if (empty(array_filter($row, fn ($v) => ! is_null($v) && trim((string) $v) !== ''))) {
                continue;
            }

            $timestamp = $colMap['timestamp'] !== null ? trim((string) ($row[$colMap['timestamp']] ?? '')) : '';
            $region = $colMap['region'] !== null ? trim((string) ($row[$colMap['region']] ?? '')) : '';
            $idReal = $colMap['id_real'] !== null ? trim((string) ($row[$colMap['id_real']] ?? '')) : '';
            $dealerName = $colMap['dealer_name'] !== null ? trim((string) ($row[$colMap['dealer_name']] ?? '')) : '';
            $programName = $colMap['program_name'] !== null ? trim((string) ($row[$colMap['program_name']] ?? '')) : '';
            $salesName = $colMap['sales_name'] !== null ? trim((string) ($row[$colMap['sales_name']] ?? '')) : '';
            $creditNoteUrl = $colMap['credit_note'] !== null ? trim((string) ($row[$colMap['credit_note']] ?? '')) : null;
            $agreementUrl = $colMap['agreement'] !== null ? trim((string) ($row[$colMap['agreement']] ?? '')) : null;
            $taxInvoiceUrl = $colMap['tax_invoice'] !== null ? trim((string) ($row[$colMap['tax_invoice']] ?? '')) : null;

            if (preg_match('/^([^\r\n\t]+)/', $idReal, $m)) {
                $idReal = trim($m[1]);
            }

            if (empty($dealerName) && empty($idReal) && empty($programName)) {
                continue;
            }

            // Generate deterministic unique hash
            $hashString = "{$timestamp}|{$region}|{$idReal}|{$dealerName}|{$programName}";
            $rowHash = sha1($hashString);

            $existing = ProgramSubmission::where('row_hash', $rowHash)->first();

            $data = [
                'submission_timestamp' => $timestamp,
                'region' => $region,
                'id_real' => $idReal,
                'dealer_name' => $dealerName,
                'program_name' => $programName,
                'sales_name' => $salesName,
                'credit_note_url' => $creditNoteUrl,
                'agreement_url' => $agreementUrl,
                'tax_invoice_url' => $taxInvoiceUrl,
                'raw_data' => $row,
            ];

            if ($existing) {
                $existing->update($data);
                $updatedCount++;
            } else {
                $data['row_hash'] = $rowHash;
                ProgramSubmission::create($data);
                $newCount++;
            }
        }

        return [
            'total_rows' => $totalRows,
            'synced_count' => $newCount + $updatedCount,
            'new_count' => $newCount,
            'updated_count' => $updatedCount,
            'message' => "Sinkronisasi berhasil ({$newCount} data baru, {$updatedCount} data diperbarui).",
        ];
    }

    /**
     * Save submission from Webhook (e.g. onFormSubmit).
     */
    public function saveWebhookPayload(array $payload): ProgramSubmission
    {
        $namedValues = $payload['namedValues'] ?? [];
        $values = $payload['values'] ?? [];

        $extract = function (array $aliases) use ($namedValues) {
            foreach ($aliases as $alias) {
                foreach ($namedValues as $key => $val) {
                    if (str_contains(strtolower(trim((string) $key)), $alias)) {
                        return is_array($val) ? ($val[0] ?? '') : $val;
                    }
                }
            }

            return '';
        };

        $timestamp = $extract(['timestamp', 'waktu', 'tanggal']);
        $region = $extract(['region', 'wilayah']);
        $idReal = $extract(['id real', 'id_real', 'id realme', 'id']);
        $dealerName = $extract(['nama dealer', 'dealer', 'toko']);
        $programName = $extract(['nama program', 'program']);
        $salesName = $extract(['nama sales', 'sales']);
        $creditNoteUrl = $extract(['credit note', 'dokumen credit', 'cn']);
        $agreementUrl = $extract(['agreement', 'perjanjian']);
        $taxInvoiceUrl = $extract(['faktur pajak', 'faktur', 'tax invoice']);

        // Fallback to array values if namedValues was empty
        if (empty($dealerName) && ! empty($values)) {
            $timestamp = $values[0] ?? date('d/m/Y H:i:s');
            $region = $values[1] ?? '';
            $idReal = $values[2] ?? '';
            $dealerName = $values[3] ?? '';
            $programName = $values[4] ?? '';
            $salesName = $values[5] ?? '';
            $creditNoteUrl = $values[6] ?? null;
            $agreementUrl = $values[7] ?? null;
            $taxInvoiceUrl = $values[8] ?? null;
        }

        $hashString = "{$timestamp}|{$region}|{$idReal}|{$dealerName}|{$programName}";
        $rowHash = sha1($hashString);

        return ProgramSubmission::updateOrCreate(
            ['row_hash' => $rowHash],
            [
                'submission_timestamp' => $timestamp ?: date('d/m/Y H:i:s'),
                'region' => $region,
                'id_real' => $idReal,
                'dealer_name' => $dealerName,
                'program_name' => $programName,
                'sales_name' => $salesName,
                'credit_note_url' => $creditNoteUrl ?: null,
                'agreement_url' => $agreementUrl ?: null,
                'tax_invoice_url' => $taxInvoiceUrl ?: null,
                'raw_data' => $payload,
            ]
        );
    }

    protected function findHeaderIndex(array $headers, array $candidates): ?int
    {
        foreach ($headers as $idx => $header) {
            foreach ($candidates as $candidate) {
                if (str_contains($header, $candidate)) {
                    return $idx;
                }
            }
        }

        return null;
    }
}

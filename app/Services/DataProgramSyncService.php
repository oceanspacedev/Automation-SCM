<?php

namespace App\Services;

use App\Area;
use App\Models\Brand;
use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\Draft;
use App\Models\MasterProgram;
use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataProgramSyncService
{
    public const CACHE_KEY_SPREADSHEET_URL = 'data_program_spreadsheet_url';

    public const DEFAULT_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/1AFEAxjycq50heaxJeAH9YjpVWFs3GeprplJDOJMzjSU/export?format=csv&gid=241795572';

    /**
     * Get configured Google Sheet CSV URL of a brand & area (defaults to the brand & area selected for the request).
     * Only Realme Jawa Barat has a built-in default spreadsheet; others must be configured first.
     */
    public function getSpreadsheetUrl(?Area $area = null, ?string $brand = null): string
    {
        $area ??= $this->currentArea();
        $brand ??= $this->currentBrand();
        $isDefaultScope = Brand::isDefaultScope($area, $brand);

        $url = (string) Cache::get(Brand::settingKey(self::CACHE_KEY_SPREADSHEET_URL, $area, $brand), $isDefaultScope ? env('DATA_PROGRAM_SPREADSHEET_URL', '') : '');

        if (! empty($url)) {
            return $url;
        }

        return $isDefaultScope ? self::DEFAULT_SPREADSHEET_URL : '';
    }

    public const CACHE_KEY_WEBAPP_URL = 'data_program_webapp_url';

    public const DEFAULT_WEBAPP_URL = 'https://script.google.com/macros/s/AKfycbwJRdSbhKwKWlU4T6Haxu1oWwiYQLef4c-IKYyAVd_sIAY2Z2UukEFQirWKox5qDebtXQ/exec';

    /**
     * Get configured Google Apps Script Web App URL of a brand & area for pushing updates.
     */
    public function getWebAppUrl(?Area $area = null, ?string $brand = null): string
    {
        $area ??= $this->currentArea();
        $brand ??= $this->currentBrand();
        $isDefaultScope = Brand::isDefaultScope($area, $brand);

        $url = (string) Cache::get(Brand::settingKey(self::CACHE_KEY_WEBAPP_URL, $area, $brand), $isDefaultScope ? env('DATA_PROGRAM_WEBAPP_URL', '') : '');
        if (! empty($url) && str_contains($url, 'script.google.com/macros/s/')) {
            return $url;
        }

        $sheetUrl = (string) Cache::get(Brand::settingKey(self::CACHE_KEY_SPREADSHEET_URL, $area, $brand), $isDefaultScope ? env('DATA_PROGRAM_SPREADSHEET_URL', '') : '');
        if (! empty($sheetUrl) && str_contains($sheetUrl, 'script.google.com/macros/s/')) {
            return $sheetUrl;
        }

        return $isDefaultScope ? self::DEFAULT_WEBAPP_URL : '';
    }

    /**
     * Set configured Google Apps Script Web App URL of the selected brand & area.
     */
    public function setWebAppUrl(string $url, ?Area $area = null): void
    {
        Cache::forever(Brand::settingKey(self::CACHE_KEY_WEBAPP_URL, $area ?? $this->currentArea(), $this->currentBrand()), trim($url));
    }

    /**
     * Set configured Google Sheet CSV URL of the selected brand & area.
     */
    public function setSpreadsheetUrl(string $url, ?Area $area = null): void
    {
        Cache::forever(Brand::settingKey(self::CACHE_KEY_SPREADSHEET_URL, $area ?? $this->currentArea(), $this->currentBrand()), trim($url));
    }

    /**
     * The area selected for the current request (Jawa Barat when none is selected).
     */
    protected function currentArea(): Area
    {
        return app(AreaContext::class)->resolve();
    }

    /**
     * The brand selected for the current request (Realme when none is selected).
     */
    protected function currentBrand(): string
    {
        return app(BrandContext::class)->resolve();
    }

    /**
     * Run a callback with an area & brand selected (the current ones by default),
     * so spreadsheet syncs only create, update, and delete rows of that area & brand.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function inScope(Closure $callback, ?Area $area = null, ?string $brand = null): mixed
    {
        return app(AreaContext::class)->run(
            $area ?? $this->currentArea(),
            fn () => app(BrandContext::class)->run($brand ?? $this->currentBrand(), $callback)
        );
    }

    /**
     * Whether both an area and a brand are selected (so queries are limited to them).
     */
    protected function isScoped(): bool
    {
        return app(AreaContext::class)->current() !== null && app(BrandContext::class)->current() !== null;
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

        // Handle negative numbers like (200.000) or -200.000
        $isNegative = str_starts_with($clean, '(') && str_ends_with($clean, ')');
        if ($isNegative) {
            $clean = trim($clean, '()');
        } elseif (str_starts_with($clean, '-')) {
            $isNegative = true;
            $clean = ltrim($clean, '-');
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
        $area = $this->currentArea();
        $url = trim((string) ($customUrl ?: ($this->getSpreadsheetUrl($area) ?: $this->getWebAppUrl($area))));

        if (empty($url)) {
            throw new Exception("URL Google Spreadsheet Data Program {$area->label()} belum diatur.");
        }

        // Selalu jalankan dalam konteks area & brand agar penghapusan baris hanya menyentuh data area & brand ini
        return $this->inScope(function () use ($url, $limit) {
            if (str_contains($url, 'script.google.com/macros/s/')) {
                return $this->syncFromWebAppUrl($url, $limit);
            }

            return $this->syncFromCsvUrl($url, $limit);
        });
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
                            'is_full_sync' => ($limit === 0),
                        ];

                        $result = $this->saveWebhookPayload($payload);

                        $msg = "Sinkronisasi berhasil ({$result['saved_count']} baris data diproses";
                        if (! empty($result['deleted_count'])) {
                            $msg .= ", {$result['deleted_count']} baris dihapus";
                        }
                        $msg .= ' dari Google Apps Script).';

                        return [
                            'total_rows' => count($dataRows),
                            'synced_count' => $result['saved_count'],
                            'new_count' => $result['saved_count'],
                            'deleted_count' => $result['deleted_count'] ?? 0,
                            'updated_count' => 0,
                            'message' => $msg,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Sinkronisasi Apps Script doGet tidak merespon/timeout ({$e->getMessage()}), beralih otomatis ke CSV export cepat.");
        }

        // Otomatis fallback ke Google Sheet CSV export stream (jauh lebih cepat untuk 21.000+ baris data)
        $csvUrl = Brand::isDefaultScope($this->currentArea(), $this->currentBrand())
            ? self::DEFAULT_SPREADSHEET_URL
            : $this->getSpreadsheetUrl();

        if (empty($csvUrl) || str_contains($csvUrl, 'script.google.com/macros/s/')) {
            throw new Exception("Gagal mengambil data dari Google Apps Script {$this->currentArea()->label()} dan link CSV spreadsheet area ini belum diatur.");
        }

        return $this->syncFromCsvUrl($csvUrl, $limit);
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
    public function pushUpdatesToSpreadsheet(array $rowsToUpdate, ?string $customUrl = null, ?Area $area = null, ?string $brand = null): array
    {
        $url = trim((string) ($customUrl ?: $this->getWebAppUrl($area, $brand)));

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
     * Fill the draft's program code (e.g. "PROGRAM 342") and empty period from Master Program by program name.
     */
    protected function resolveProgramFromMaster(Draft $draft): ?MasterProgram
    {
        $master = MasterProgram::where('name', trim((string) $draft->program_name))->first();

        if (! $master) {
            return null;
        }

        if ($master->code) {
            $draft->program_code = $master->code;
        }

        if (empty($draft->program_period) && $master->period) {
            $draft->program_period = $master->period;
        }

        if ($draft->isDirty(['program_code', 'program_period'])) {
            $draft->save();
        }

        return $master;
    }

    /**
     * The Master Dealer of a draft: by kode BT first (unique), then by ID Realme.
     */
    protected function findDealerForDraft(Draft $draft): ?Dealer
    {
        $kodeBt = Dealer::normalizeKodeBt($draft->kode_bt);
        if ($kodeBt && ($dealer = Dealer::withoutGlobalScope('area')->where('kode_bt', $kodeBt)->first())) {
            return $dealer;
        }

        $dealerCode = trim((string) $draft->dealer_code);
        if ($dealerCode === '') {
            return null;
        }

        return Dealer::withoutGlobalScope('area')->where('kode_bt', Dealer::normalizeKodeBt($dealerCode))->first()
            ?? Dealer::withoutGlobalScope('area')->where('id_realme', $dealerCode)->first();
    }

    /**
     * Synchronize a Draft invoice record into DataProgram (local DB and Google Spreadsheet).
     *
     * @return array{success: bool, data_program: DataProgram, sheet_push: ?array, message: string}
     */
    public function syncDraftToDataProgram(Draft $draft, bool $pushToSpreadsheet = true): array
    {
        // Data Program draft disimpan & dikirim ke spreadsheet milik area & brand draft tersebut
        if (app(AreaContext::class)->current() !== $draft->areaEnum() || app(BrandContext::class)->current() !== $draft->brand) {
            return $this->inScope(fn () => $this->syncDraftToDataProgram($draft, $pushToSpreadsheet), $draft->areaEnum(), $draft->brand);
        }

        $rowHash = sprintf('draft_%d', $draft->id);

        $this->resolveProgramFromMaster($draft);

        $inputDateStr = $draft->invoice_date ?: date('Y-m-d');
        $daysPending = 0;
        try {
            $inputDate = Carbon::parse($inputDateStr);
            $daysPending = max(0, (int) $inputDate->diffInDays(now()));
        } catch (\Throwable $e) {
            $daysPending = 0;
        }

        $keteranganAging = $daysPending > 30 ? 'LEBIH DARI 30 HARI' : 'KURANG DARI 30 HARI';

        $attributes = [
            'draft_id' => $draft->id,
            'dealer_name' => $draft->dealer_name,
            'program' => $draft->program_code,
            'kode_bt' => $draft->kode_bt ?: $draft->dealer_code,
            'program_name' => $draft->program_name,
            'periode' => $draft->program_period,
            'region' => $draft->region,
            'big_region' => $draft->region,
            'id_gs' => $draft->dealer_code,
            'wajib_pajak' => $draft->npwp_type ?: 'BADAN',
            'trf_pph' => $draft->pph_type ?: 'BADAN',
            'incentive' => (float) ($draft->support_amount ?: 0),
            'dpp' => (float) ($draft->dpp ?: 0),
            'dpp_lain' => (float) ($draft->dpp_lain ?: 0),
            'ppn' => (float) ($draft->ppn ?: 0),
            'nilai_pph' => (float) ($draft->pph ?: 0),
            'net_pay' => (float) ($draft->netpay ?: 0),
            'tgl_input' => $inputDateStr,
            'lama_pending' => (string) $daysPending,
            'keterangan' => $keteranganAging,
            'noted' => $draft->ref_note,
            'npwp' => $draft->npwp,
            'nama_npwp' => $draft->npwp_name,
            // Catatan: Kolom 'cn' di Data Program sengaja dikosongkan karena jalurnya khusus dari hasil matching Form Program (Test Program)
        ];

        // Sales & Telemarketing mengikuti Master Dealer (per toko); kosong di dealer tidak menimpa isian manual di Data Program
        $dealer = $this->findDealerForDraft($draft);
        foreach (['sales_person', 'telemarketing', 'telemarketing_phone'] as $column) {
            if (! empty($dealer?->{$column})) {
                $attributes[$column] = $dealer->{$column};
            }
        }

        $dp = DataProgram::where('draft_id', $draft->id)
            ->orWhere('row_hash', $rowHash)
            ->first();

        if ($dp) {
            $dp->update($attributes);
        } else {
            $dp = DataProgram::create(array_merge($attributes, [
                'row_hash' => $rowHash,
                'cek_dokumen' => 'BELUM MATCH TEST',
                'status_potong_purchase' => 'BELUM BISA POTONG',
                'status_potong_ar' => 'BELUM POTONG',
            ]));
        }

        $sheetPushResult = null;
        if ($pushToSpreadsheet) {
            try {
                $sheetPushResult = $this->pushDraftsToSpreadsheet([$dp]);
            } catch (\Throwable $e) {
                Log::warning('Push draft to spreadsheet failed: '.$e->getMessage());
            }
        }

        return [
            'success' => true,
            'data_program' => $dp,
            'sheet_push' => $sheetPushResult,
            'message' => 'Data draft berhasil disinkronkan ke Data Program'.($sheetPushResult && ($sheetPushResult['success'] ?? false) ? ' dan Google Spreadsheet.' : '.'),
        ];
    }

    /**
     * Convert a DataProgram model to a 56-column row array for Google Spreadsheet.
     *
     * @return array<int, mixed>
     */
    public function convertDataProgramToRowArray(DataProgram $dp): array
    {
        return [
            $dp->dealer_name ?? '',
            $dp->program ?? '',
            $dp->kode_bt ?? '',
            $dp->program_name ?? '',
            $dp->periode ?? '',
            $dp->region ?? '',
            $dp->no_po ?? '',
            $dp->id_gs ?? '',
            $dp->kode_supplier ?? '',
            $dp->status_dl ?? '',
            $dp->sales_person ?? '',
            $dp->telemarketing ?? '',
            $dp->wajib_pajak ?? '',
            $dp->trf_pph ?? '',
            $dp->incentive ? number_format($dp->incentive, 0, ',', '.') : '',
            $dp->dpp ? number_format($dp->dpp, 0, ',', '.') : '',
            $dp->dpp_lain ? number_format($dp->dpp_lain, 0, ',', '.') : '',
            $dp->ppn ? number_format($dp->ppn, 0, ',', '.') : '',
            $dp->nilai_pph ? number_format($dp->nilai_pph, 0, ',', '.') : '',
            $dp->net_pay ? number_format($dp->net_pay, 0, ',', '.') : '',
            $dp->cek_pajak_tarif ?? '',
            $dp->selisih ? number_format($dp->selisih, 0, ',', '.') : '',
            $dp->note_pph ?? '',
            $dp->no_faktur_pajak ?? '',
            $dp->ket_faktur_pajak ?? '',
            $dp->no_po_sj ?? '',
            $dp->no_transaksi ?? '',
            $dp->tgl_input ?? '',
            $dp->tgl_share_cn ?? '',
            $dp->lama_pending ?? '',
            $dp->keterangan ?? '',
            $dp->cek_dokumen ?? '',
            $dp->status_potong_purchase ?? '',
            $dp->status_potong_ar ?? '',
            $dp->tgl_potong_tf ?? '',
            $dp->no_uid ?? '',
            $dp->no_pembayaran ?? '',
            $dp->tgl_input_bank_pph ?? '',
            $dp->tf_status ?? '',
            $dp->tgl_proses ?? '',
            $dp->tgl_sj ?? '',
            $dp->no_sj ?? '',
            $dp->info_bank ?? '',
            $dp->pending_potongan ?? '',
            $dp->npwp ?? '',
            $dp->nama_npwp ?? '',
            $dp->program_2 ?? '',
            $dp->cn ?? '',
            $dp->agrement ?? '',
            $dp->cek_fp ?? '',
            $dp->cek_evidance ?? '',
            $dp->noted ?? '',
            $dp->norek ?? '',
            $dp->namrek ?? '',
            $dp->bank ?? '',
            $dp->big_region ?? '',
        ];
    }

    /**
     * A Data Program row in the shape the spreadsheet's Apps Script "update_rows" action expects.
     *
     * @return array<string, mixed>
     */
    public function spreadsheetRow(DataProgram $dp): array
    {
        $rowIndex = str_starts_with((string) $dp->row_hash, 'row_') ? (int) str_replace('row_', '', (string) $dp->row_hash) : 0;

        return [
            'row_index' => $rowIndex > 0 ? $rowIndex : null,
            'kode_bt' => $dp->kode_bt,
            'dealer_name' => $dp->dealer_name,
            'program' => $dp->program,
            'program_name' => $dp->program_name,
            'periode' => $dp->periode,
            'region' => $dp->region,
            'no_po' => $dp->no_po,
            'id_gs' => $dp->id_gs,
            'kode_supplier' => $dp->kode_supplier,
            'status_dl' => $dp->status_dl,
            'sales_person' => $dp->sales_person,
            'telemarketing' => $dp->telemarketing,
            'wajib_pajak' => $dp->wajib_pajak,
            'trf_pph' => $dp->trf_pph,
            'incentive' => $dp->incentive,
            'dpp' => $dp->dpp,
            'dpp_lain' => $dp->dpp_lain,
            'ppn' => $dp->ppn,
            'nilai_pph' => $dp->nilai_pph,
            'net_pay' => $dp->net_pay,
            'cek_pajak_tarif' => $dp->cek_pajak_tarif,
            'selisih' => $dp->selisih,
            'note_pph' => $dp->note_pph,
            'no_faktur_pajak' => $dp->no_faktur_pajak,
            'ket_faktur_pajak' => $dp->ket_faktur_pajak,
            'no_po_sj' => $dp->no_po_sj,
            'no_transaksi' => $dp->no_transaksi,
            'tgl_input' => $dp->tgl_input,
            'tgl_share_cn' => $dp->tgl_share_cn,
            'lama_pending' => $dp->lama_pending,
            'status_potong_purchase' => $dp->status_potong_purchase,
            'status_potong_ar' => $dp->status_potong_ar,
            'tgl_potong_tf' => $dp->tgl_potong_tf,
            'no_uid' => $dp->no_uid,
            'no_pembayaran' => $dp->no_pembayaran,
            'tgl_input_bank_pph' => $dp->tgl_input_bank_pph,
            'tf_status' => $dp->tf_status,
            'tgl_proses' => $dp->tgl_proses,
            'tgl_sj' => $dp->tgl_sj,
            'no_sj' => $dp->no_sj,
            'info_bank' => $dp->info_bank,
            'pending_potongan' => $dp->pending_potongan,
            'npwp' => $dp->npwp,
            'nama_npwp' => $dp->nama_npwp,
            'program_2' => $dp->program_2,
            'cek_dokumen' => $dp->cek_dokumen,
            'keterangan' => $dp->keterangan,
            'cn' => $dp->cn,
            'agrement' => $dp->agrement,
            'cek_fp' => $dp->cek_fp,
            'cek_evidance' => $dp->cek_evidance,
            'noted' => $dp->noted,
            'norek' => $dp->norek,
            'namrek' => $dp->namrek,
            'bank' => $dp->bank,
            'big_region' => $dp->big_region,
            'values' => $this->convertDataProgramToRowArray($dp),
        ];
    }

    /**
     * Fill a Master Program's code into the Data Program rows and drafts of that program name that have no code yet
     * (or still have the program's previous code), then send the changed rows to their area's spreadsheet like an edit
     * in Data Program does.
     *
     * @return int how many Data Program rows got the code
     */
    public function fillProgramCode(MasterProgram $program, ?string $previousCode = null): int
    {
        $code = trim((string) $program->code);
        $name = trim((string) $program->name);

        if ($code === '' || $name === '') {
            return 0;
        }

        $replaceable = array_values(array_unique(['', '-', ...($previousCode && $previousCode !== $code ? [$previousCode] : [])]));
        $withoutCode = fn (string $column) => fn ($query) => $query->whereNull($column)->orWhereIn($column, $replaceable);

        // Draft ikut diisi, supaya sinkron ulang draft ke Data Program tidak mengosongkan kodenya lagi
        Draft::withoutGlobalScopes()
            ->where('brand', $program->brand)
            ->where('program_name', $name)
            ->where($withoutCode('program_code'))
            ->update(['program_code' => $code]);

        $rows = DataProgram::withoutGlobalScopes()
            ->where('brand', $program->brand)
            ->where('program_name', $name)
            ->where($withoutCode('program'))
            ->get();

        foreach ($rows as $row) {
            $row->forceFill(['program' => $code])->save();
        }

        foreach ($rows->groupBy('area') as $area => $areaRows) {
            try {
                $this->pushUpdatesToSpreadsheet($areaRows->map(fn (DataProgram $row) => $this->spreadsheetRow($row))->values()->all(), null, Area::from($area), $program->brand);
            } catch (\Throwable $e) {
                Log::warning("Push kode program {$code} ke spreadsheet gagal: {$e->getMessage()}");
            }
        }

        return $rows->count();
    }

    /**
     * Push draft DataProgram records to Google Spreadsheet via Apps Script Web App.
     *
     * @param  array<int, DataProgram>  $dataPrograms
     * @return array{success: bool, count: int, message: string}
     */
    public function pushDraftsToSpreadsheet(array $dataPrograms, ?string $customUrl = null): array
    {
        // Kirim tiap brand & area ke spreadsheet masing-masing
        $groups = collect($dataPrograms)
            ->filter(fn ($dp) => $dp instanceof DataProgram)
            ->groupBy(fn (DataProgram $dp) => $dp->brand.'|'.$dp->areaEnum()->value);

        if ($customUrl === null && $groups->count() > 1) {
            $results = $groups->map(fn ($group) => $this->pushDraftsToSpreadsheet($group->all()));

            return [
                'success' => $results->every(fn ($result) => $result['success']),
                'count' => $results->sum('count'),
                'message' => $results->pluck('message')->implode(' '),
            ];
        }

        [$brand, $area] = $groups->isNotEmpty() ? explode('|', (string) $groups->keys()->first()) : [null, null];
        $url = trim((string) ($customUrl ?: $this->getWebAppUrl($area ? Area::from($area) : null, $brand)));

        if (empty($url) || ! str_contains($url, 'script.google.com/macros/s/')) {
            return [
                'success' => false,
                'count' => 0,
                'message' => 'URL Google Apps Script Web App belum diatur atau bukan Web App Apps Script.',
            ];
        }

        $rows = [];
        foreach ($dataPrograms as $dp) {
            if (! $dp instanceof DataProgram) {
                continue;
            }

            $rowIndex = null;
            if (str_starts_with((string) $dp->row_hash, 'row_')) {
                $rowIndex = (int) str_replace('row_', '', (string) $dp->row_hash);
            }

            $rows[] = [
                'row_index' => $rowIndex,
                'dealer_name' => $dp->dealer_name,
                'program' => $dp->program,
                'kode_bt' => $dp->kode_bt,
                'program_name' => $dp->program_name,
                'periode' => $dp->periode,
                'region' => $dp->region,
                'id_gs' => $dp->id_gs,
                'kode_supplier' => $dp->kode_supplier,
                'wajib_pajak' => $dp->wajib_pajak,
                'trf_pph' => $dp->trf_pph,
                'incentive' => $dp->incentive,
                'dpp' => $dp->dpp,
                'dpp_lain' => $dp->dpp_lain,
                'ppn' => $dp->ppn,
                'nilai_pph' => $dp->nilai_pph,
                'net_pay' => $dp->net_pay,
                'tgl_input' => $dp->tgl_input,
                'keterangan' => $dp->keterangan,
                'cek_dokumen' => $dp->cek_dokumen,
                'status_potong_purchase' => $dp->status_potong_purchase,
                'status_potong_ar' => $dp->status_potong_ar,
                'npwp' => $dp->npwp,
                'nama_npwp' => $dp->nama_npwp,
                'cn' => $dp->cn,
                'big_region' => $dp->big_region,
                'values' => $this->convertDataProgramToRowArray($dp),
            ];
        }

        if (empty($rows)) {
            return [
                'success' => true,
                'count' => 0,
                'message' => 'Tidak ada data draft yang perlu dikirim ke spreadsheet.',
            ];
        }

        try {
            $response = Http::withoutVerifying()
                ->withOptions(['allow_redirects' => true])
                ->timeout(120)
                ->post($url, [
                    'action' => 'append_rows',
                    'rows' => $rows,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'count' => count($rows),
                    'message' => 'Berhasil mengirim '.count($rows).' baris data ke Google Spreadsheet.',
                ];
            }

            // Fallback: update_rows action
            $fallback = Http::withoutVerifying()
                ->withOptions(['allow_redirects' => true])
                ->timeout(120)
                ->post($url, [
                    'action' => 'update_rows',
                    'rows' => $rows,
                ]);

            return [
                'success' => $fallback->successful(),
                'count' => count($rows),
                'message' => $fallback->successful()
                    ? 'Berhasil mengirim '.count($rows).' baris ke Google Spreadsheet.'
                    : 'Gagal mengirim ke Google Spreadsheet.',
            ];
        } catch (\Throwable $e) {
            Log::warning('Push drafts to spreadsheet failed: '.$e->getMessage());

            return [
                'success' => false,
                'count' => 0,
                'message' => 'Gagal mengirim ke Google Spreadsheet: '.$e->getMessage(),
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
        if (! $this->isScoped()) {
            return $this->inScope(fn () => $this->syncFromCsvUrl($url, $limit));
        }

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

        $batch = [];
        $batchSize = 400;
        $totalProcessed = 0;
        $rowIdx = 0;
        $headerFound = false;
        $headerRowIdx = 0;

        $syncTimestamp = now()->subSecond()->toDateTimeString();
        $now = now()->toDateTimeString();

        $maxRowProcessed = 0;
        $emptyRowHashes = [];

        try {
            DB::beginTransaction();

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rowIdx++;

                // Cari baris header kolom jika belum ditemukan (lewati title banner/baris kosong di atas header)
                if (! $headerFound) {
                    $firstCol = trim((string) ($row[0] ?? ''));
                    $secondCol = trim((string) ($row[1] ?? ''));
                    $thirdCol = trim((string) ($row[2] ?? ''));
                    $fourthCol = trim((string) ($row[3] ?? ''));

                    if (
                        strcasecmp($firstCol, 'NAMA DEALER') === 0 ||
                        stripos($firstCol, 'DEALER') !== false ||
                        stripos($secondCol, 'PROGRAM') !== false ||
                        stripos($thirdCol, 'BT') !== false ||
                        stripos($fourthCol, 'PROGRAM') !== false
                    ) {
                        $headerFound = true;
                        $headerRowIdx = $rowIdx;
                        $maxRowProcessed = $rowIdx;

                        continue;
                    }

                    continue;
                }

                $maxRowProcessed = $rowIdx;

                $mapped = $this->mapRowToData($row, $rowIdx, $now);
                if (! $mapped) {
                    $emptyRowHashes[] = sprintf('row_%d', $rowIdx);

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

            if (! $headerFound && $rowIdx > 0) {
                throw new Exception('Format kolom spreadsheet tidak valid atau kolom header tidak ditemukan.');
            }

            if (! empty($batch)) {
                $this->upsertBatch($batch);
            }

            // Hapus baris yang dikosongkan di dalam spreadsheet
            if (! empty($emptyRowHashes)) {
                DataProgram::whereIn('row_hash', $emptyRowHashes)->delete();
            }

            // Hapus data di database yang barisnya sudah dihapus dari Spreadsheet (melebihi jumlah baris sheet atau baris banner/header)
            if ($limit === 0 && $maxRowProcessed >= 2) {
                if ($headerRowIdx >= 1) {
                    DataProgram::whereRaw('CAST(SUBSTRING(row_hash, 5) AS UNSIGNED) <= ?', [$headerRowIdx])->delete();
                }
                DataProgram::whereRaw('CAST(SUBSTRING(row_hash, 5) AS UNSIGNED) > ?', [$maxRowProcessed])->delete();
                DataProgram::where('row_hash', 'not like', 'row_%')->delete();
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

        if (
            strcasecmp($dealerName, 'NAMA DEALER') === 0 ||
            strcasecmp($dealerName, 'DEALER') === 0 ||
            stripos($dealerName, 'MASTER DATA') !== false
        ) {
            return null;
        }

        if (empty($dealerName) && empty($program) && empty($kodeBt) && empty($programName)) {
            return null;
        }

        // Identifier konsisten berdasarkan nomor baris spreadsheet agar saat data diubah tidak membuat baris duplikat baru
        $rowHash = sprintf('row_%d', $rowIdx);
        $now = $now ?? now()->toDateTimeString();

        return [
            'area' => $this->currentArea()->value,
            'brand' => $this->currentBrand(),
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
        if (! $this->isScoped()) {
            return $this->inScope(fn () => $this->saveWebhookPayload($payload));
        }

        $batch = [];
        $now = now()->toDateTimeString();
        $deletedCount = 0;

        // Support explicit action === 'delete' from Webhook
        if (isset($payload['action']) && $payload['action'] === 'delete') {
            $rowIdx = (int) ($payload['row_index'] ?? ($payload['rowNumber'] ?? 0));
            if ($rowIdx > 0) {
                $deleted = DataProgram::where('row_hash', sprintf('row_%d', $rowIdx))->delete();

                return [
                    'saved_count' => 0,
                    'deleted_count' => $deleted ? 1 : 0,
                    'message' => "Baris row_{$rowIdx} berhasil dihapus dari Data Program.",
                ];
            }
        }

        if (isset($payload['values']) && is_array($payload['values'])) {
            $rowIdx = (int) ($payload['row_index'] ?? ($payload['rowNumber'] ?? 2));
            $mapped = $this->mapRowToData($payload['values'], $rowIdx, $now);
            if ($mapped) {
                $batch[] = $mapped;
            } else {
                // Baris dikosongkan/dihapus di spreadsheet -> hapus dari database
                $deleted = DataProgram::where('row_hash', sprintf('row_%d', $rowIdx))->delete();

                return [
                    'saved_count' => 0,
                    'deleted_count' => $deleted ? 1 : 0,
                    'message' => "Baris row_{$rowIdx} berhasil dihapus karena data dikosongkan di spreadsheet.",
                ];
            }
        } elseif (isset($payload['row']) && is_array($payload['row'])) {
            $rowIdx = (int) ($payload['row_index'] ?? ($payload['rowNumber'] ?? 2));
            $mapped = $this->mapRowToData($payload['row'], $rowIdx, $now);
            if ($mapped) {
                $batch[] = $mapped;
            } else {
                $deleted = DataProgram::where('row_hash', sprintf('row_%d', $rowIdx))->delete();

                return [
                    'saved_count' => 0,
                    'deleted_count' => $deleted ? 1 : 0,
                    'message' => "Baris row_{$rowIdx} berhasil dihapus karena data dikosongkan di spreadsheet.",
                ];
            }
        } elseif (isset($payload['rows']) && is_array($payload['rows'])) {
            $startRow = (int) ($payload['start_row'] ?? ($payload['startRow'] ?? 2));
            $isFullSync = (bool) ($payload['is_full_sync'] ?? false);
            $emptyRowHashes = [];

            foreach ($payload['rows'] as $i => $row) {
                if (is_array($row)) {
                    $rowIdx = $startRow + $i;
                    $mapped = $this->mapRowToData($row, $rowIdx, $now);
                    if ($mapped) {
                        $batch[] = $mapped;
                    } else {
                        $emptyRowHashes[] = sprintf('row_%d', $rowIdx);
                    }
                }
            }

            if (! empty($emptyRowHashes)) {
                $deletedCount += DataProgram::whereIn('row_hash', $emptyRowHashes)->delete();
            }

            if ($isFullSync && count($payload['rows']) > 0) {
                $maxRowIdx = $startRow + count($payload['rows']) - 1;
                $deletedCount += DataProgram::whereRaw('CAST(SUBSTRING(row_hash, 5) AS UNSIGNED) > ?', [$maxRowIdx])->delete();
                $deletedCount += DataProgram::where('row_hash', 'not like', 'row_%')->delete();
            }
        } elseif (array_is_list($payload) && ! empty($payload) && is_array($payload[0])) {
            foreach ($payload as $i => $row) {
                if (is_array($row)) {
                    $rowIdx = 2 + $i;
                    $mapped = $this->mapRowToData($row, $rowIdx, $now);
                    if ($mapped) {
                        $batch[] = $mapped;
                    } else {
                        $deletedCount += DataProgram::where('row_hash', sprintf('row_%d', $rowIdx))->delete();
                    }
                }
            }
        }

        if (empty($batch)) {
            return [
                'saved_count' => 0,
                'deleted_count' => $deletedCount,
                'message' => $deletedCount > 0
                    ? "Berhasil menghapus {$deletedCount} baris data yang kosong/dihapus di spreadsheet."
                    : 'Tidak ada baris data valid yang diproses dari webhook.',
            ];
        }

        foreach (array_chunk($batch, 300) as $chunk) {
            $this->upsertBatch($chunk);
        }

        // Otomatis rekonsiliasi data baru/edit dengan Form Program & kirim balik ke Spreadsheet secara realtime
        $rowsToPush = [];
        try {
            $reconciliationService = app(ProgramReconciliationService::class);
            foreach ($batch as $item) {
                $dp = DataProgram::where('row_hash', $item['row_hash'])->first();
                if (! $dp) {
                    continue;
                }

                $res = $reconciliationService->reconcileSingle($dp, null, false);
                if ($res['success']) {
                    $dp->refresh();
                    $rowIndex = (int) str_replace('row_', '', (string) $dp->row_hash);
                    $rowsToPush[] = [
                        'row_index' => $rowIndex > 0 ? $rowIndex : null,
                        'kode_bt' => $dp->kode_bt,
                        'dealer_name' => $dp->dealer_name,
                        'program' => $dp->program,
                        'program_name' => $dp->program_name,
                        'periode' => $dp->periode,
                        'status_potong_purchase' => $dp->status_potong_purchase,
                        'status_potong_ar' => $dp->status_potong_ar,
                        'tgl_potong_tf' => $dp->tgl_potong_tf,
                        'cek_dokumen' => $dp->cek_dokumen,
                        'keterangan' => $dp->keterangan,
                        'cn' => $dp->cn,
                        'agrement' => $dp->agrement,
                        'cek_fp' => $dp->cek_fp,
                        'no_faktur_pajak' => $dp->no_faktur_pajak,
                        'ket_faktur_pajak' => $dp->ket_faktur_pajak,
                        'noted' => $dp->noted,
                    ];
                }
            }

            if (! empty($rowsToPush)) {
                $this->pushUpdatesToSpreadsheet($rowsToPush);
            }
        } catch (\Throwable $e) {
            Log::warning('Auto-reconcile on webhook failed: '.$e->getMessage());
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

        DataProgram::upsert($batch, ['brand', 'area', 'row_hash'], $updateColumns);
    }
}

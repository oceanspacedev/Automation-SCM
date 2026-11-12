<?php

namespace App\Services;

use App\Models\DataProgram;
use App\Models\Dealer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Fills No PO/SJ, No Transaksi and Tgl Input of Data Program rows from an Excel file (columns Kode BT, Nama Program,
 * No PO/SJ, No. Trs, and optionally Tanggal Input / Tanggal PO), matching each row by Kode BT + Nama Program in every
 * area of the current brand. The file's date becomes the row's Tgl Input; without one, a row whose No PO/SJ is filled
 * or changed, or that has a No PO/SJ but no Tgl Input yet, gets the import day. The changed rows are then sent to the
 * area's Google Spreadsheet, so the next sync from the spreadsheet keeps them.
 */
class DataProgramPoImportService
{
    /**
     * Header names per field in order of preference, compared in lower case without dots and extra spaces.
     *
     * @var array<string, list<string>>
     */
    private const HEADERS = [
        'kode_bt' => ['kode bt', 'kodebt', 'kode_bt', 'bt'],
        'program_name' => ['nama program', 'program name', 'nama_program', 'program'],
        'no_po_sj' => ['no po/sj', 'no po sj', 'nomor po/sj', 'no_po_sj', 'po/sj'],
        'no_transaksi' => ['no trs', 'no transaksi', 'nomor transaksi', 'no_transaksi', 'no trx', 'trs'],
        'tgl_input' => ['tanggal input', 'tgl input', 'tgl_input', 'tanggal po', 'tgl po'],
    ];

    /** Rows sent to the spreadsheet per request, as in the Data Program table's bulk edit. */
    private const PUSH_CHUNK = 500;

    public function __construct(protected DataProgramSyncService $syncService) {}

    /**
     * @return array{
     *     total_rows: int,
     *     updated: int,
     *     replaced: int,
     *     unchanged: int,
     *     not_found: list<array{row: int, kode_bt: string, program: string}>,
     *     sheet_push: list<array{area: string, status: 'sent'|'failed'|'not_connected', message: string}>
     * }
     */
    public function import(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        [$headerIndex, $columns] = $this->findHeader($rows);

        $fileRows = [];
        foreach (array_slice($rows, $headerIndex + 1, preserve_keys: true) as $index => $row) {
            $kodeBt = $this->cellText($row[$columns['kode_bt']] ?? null);
            $program = $this->cellText($row[$columns['program_name']] ?? null);

            if ($kodeBt === null && $program === null) {
                continue;
            }

            $fileRows[] = [
                'row' => $index + 1,
                'kode_bt' => (string) $kodeBt,
                'program' => (string) $program,
                // Sel kosong tidak menghapus nomor yang sudah ada
                'values' => array_filter([
                    'no_po_sj' => isset($columns['no_po_sj']) ? $this->cellText($row[$columns['no_po_sj']] ?? null) : null,
                    'no_transaksi' => isset($columns['no_transaksi']) ? $this->cellText($row[$columns['no_transaksi']] ?? null) : null,
                ], fn (?string $value) => $value !== null),
                'tgl_input' => isset($columns['tgl_input']) ? $this->cellDate($row[$columns['tgl_input']] ?? null) : null,
            ];
        }

        $dataPrograms = $this->candidates(array_column($fileRows, 'kode_bt'))
            ->groupBy(fn (DataProgram $dp) => $this->matchKey($dp->kode_bt, $dp->program_name));

        $changed = collect();
        $replaced = 0;
        $unchanged = 0;
        $notFound = [];

        foreach ($fileRows as $fileRow) {
            $matches = $dataPrograms->get($this->matchKey($fileRow['kode_bt'], $fileRow['program']));

            if ($matches === null) {
                $notFound[] = ['row' => $fileRow['row'], 'kode_bt' => $fileRow['kode_bt'], 'program' => $fileRow['program']];

                continue;
            }

            foreach ($matches as $dp) {
                $dp->fill($fileRow['values']);
                $replacesNumber = collect(array_keys($fileRow['values']))
                    ->contains(fn (string $field) => $dp->isDirty($field) && filled($dp->getOriginal($field)));

                // Tgl Input = tanggal di file; tanpa tanggal: hari No PO/SJ masuk, dan nomor yang sama juga mengisi Tgl Input yang masih kosong
                $dp->stampInputDate(whenMissing: isset($fileRow['values']['no_po_sj']));
                if ($fileRow['tgl_input'] !== null) {
                    $dp->tgl_input = $fileRow['tgl_input'];
                }

                if (! $dp->isDirty()) {
                    $unchanged++;

                    continue;
                }

                if ($replacesNumber) {
                    $replaced++;
                }

                $dp->save();
                $changed->put($dp->id, $dp);
            }
        }

        return [
            'total_rows' => count($fileRows),
            'updated' => $changed->count(),
            'replaced' => $replaced,
            'unchanged' => $unchanged,
            'not_found' => $notFound,
            'sheet_push' => $this->pushToSpreadsheet($changed->values()),
        ];
    }

    /**
     * The header row (within the first rows) and the column index of each field.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}
     */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, preserve_keys: true) as $index => $row) {
            $headers = array_map(fn ($cell) => trim(preg_replace('/\s+/', ' ', str_replace('.', ' ', strtolower((string) $cell)))), $row);
            $headers = array_map(fn (string $header) => preg_replace('#\s*/\s*#', '/', $header), $headers);

            $columns = [];
            foreach (self::HEADERS as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $column = array_search($alias, $headers, true);
                    if ($column !== false && ! in_array($column, $columns, true)) {
                        $columns[$field] = $column;
                        break;
                    }
                }
            }

            if (isset($columns['kode_bt'], $columns['program_name']) && (isset($columns['no_po_sj']) || isset($columns['no_transaksi']))) {
                return [$index, $columns];
            }
        }

        throw new RuntimeException('Kolom Kode BT, Nama Program, dan No PO/SJ atau No. Trs tidak ditemukan di file.');
    }

    /**
     * Data Program rows of the current brand (every area) whose Kode BT appears in the file, written "BT0…" or "BTO…".
     *
     * @param  list<string>  $kodeBts
     * @return Collection<int, DataProgram>
     */
    private function candidates(array $kodeBts): Collection
    {
        $variants = collect($kodeBts)
            ->map(fn (string $kodeBt) => Dealer::normalizeKodeBt($kodeBt))
            ->filter()
            ->flatMap(fn (string $kodeBt) => [$kodeBt, preg_replace('/^BT0/', 'BTO', $kodeBt)])
            ->unique()
            ->values();

        return $variants->isEmpty()
            ? collect()
            : DataProgram::withoutGlobalScope('area')->whereIn('kode_bt', $variants)->get();
    }

    /**
     * Kode BT ("BTO…" = "BT0…") and program name without spaces or punctuation, e.g. "BT05165|PROGRAMPEAKSEASONSEPTEMBER2026".
     */
    private function matchKey(?string $kodeBt, ?string $program): string
    {
        return Dealer::normalizeKodeBt($kodeBt).'|'.preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $program));
    }

    /**
     * A cell as text: numbers such as 2604000363 without decimals or exponent, empty cells and "-" as null.
     */
    private function cellText(mixed $value): ?string
    {
        if (is_float($value) && floor($value) === $value) {
            $value = sprintf('%.0f', $value);
        }

        $text = trim((string) $value);

        return $text === '' || $text === '-' ? null : $text;
    }

    /**
     * A date cell as "2026-04-13": an Excel date (serial number), or text written "13/04/2026", "13-04-2026" (day first,
     * as in the spreadsheet) or "2026-04-13". Null for an empty cell or text that is no date.
     */
    private function cellDate(mixed $value): ?string
    {
        if (is_numeric($value) && (float) $value >= 1 && (float) $value < 2958466) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $text = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $text, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})#', $text, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    /**
     * Send the changed rows to the Google Spreadsheet of their brand & area: "sent", "failed" (the next sync from
     * that spreadsheet may empty the numbers again), or "not_connected" (the area has no spreadsheet, so the
     * numbers only live in the app).
     *
     * @param  Collection<int, DataProgram>  $dataPrograms
     * @return list<array{area: string, status: 'sent'|'failed'|'not_connected', message: string}>
     */
    private function pushToSpreadsheet(Collection $dataPrograms): array
    {
        $overwriteWarning = 'Nomor bisa tertimpa kosong lagi saat sinkron dari spreadsheet.';
        $results = [];

        foreach ($dataPrograms->groupBy(fn (DataProgram $dp) => $dp->brand.'|'.$dp->areaEnum()->value) as $areaRows) {
            $area = $areaRows->first()->areaEnum();
            $brand = $areaRows->first()->brand;

            if ($this->syncService->getWebAppUrl($area, $brand) === '') {
                $results[] = $this->syncService->getSpreadsheetUrl($area, $brand) === ''
                    ? ['area' => $area->label(), 'status' => 'not_connected', 'message' => 'belum terhubung ke Google Spreadsheet, jadi nomor hanya tersimpan di aplikasi.']
                    : ['area' => $area->label(), 'status' => 'failed', 'message' => "link Apps Script belum diatur, jadi spreadsheet tidak ikut diperbarui. {$overwriteWarning}"];

                continue;
            }

            foreach ($areaRows->chunk(self::PUSH_CHUNK) as $chunk) {
                try {
                    $result = $this->syncService->pushUpdatesToSpreadsheet(
                        $chunk->map(fn (DataProgram $dp) => $this->syncService->spreadsheetRow($dp))->values()->all(),
                        null,
                        $area,
                        $brand
                    );
                } catch (\Throwable $e) {
                    Log::warning("Push import No PO/SJ to spreadsheet failed: {$e->getMessage()}");
                    $result = ['success' => false, 'message' => $e->getMessage()];
                }

                $results[] = $result['success']
                    ? ['area' => $area->label(), 'status' => 'sent', 'message' => 'sudah diperbarui.']
                    : ['area' => $area->label(), 'status' => 'failed', 'message' => "belum terkirim ({$result['message']}). {$overwriteWarning}"];
            }
        }

        return $results;
    }
}

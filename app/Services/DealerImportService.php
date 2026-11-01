<?php

namespace App\Services;

use App\Area;
use App\Models\Dealer;
use App\Models\Region;
use Exception;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Imports Master Dealer from the CV TOP master file (sheet "Master Dealer CV TOP").
 */
class DealerImportService
{
    /**
     * Big Area → area. Stores without a Big Area fall back to the DP in "Reffnote".
     *
     * @var array<string, Area>
     */
    public const BIG_AREA_TO_AREA = [
        'BIG BANDUNG' => Area::Jabar,
        'BIG TASIK' => Area::Jabar,
        'BIG CIREBON' => Area::Jabar,
        'BIG CPS' => Area::Jabar,
        'BIG CISUKA' => Area::Jabar,
        'BIG SEMARANG' => Area::Jateng,
        'BIG SOLO' => Area::Jateng,
        'BIG PURWOKERTO' => Area::Jateng,
        'BIG TEGAL' => Area::Jateng,
        'BIG JOGJA' => Area::Jateng,
    ];

    /**
     * @var array<string, Area>
     */
    public const REFFNOTE_TO_AREA = [
        'REALME - DP BANDUNG' => Area::Jabar,
        'REALME - DP CIREBON' => Area::Jabar,
        'REALME - DP SEMARANG' => Area::Jateng,
        'REALME - DP PURWOKERTO' => Area::Jateng,
    ];

    /**
     * Excel header → dealer attribute.
     *
     * @var array<string, string>
     */
    protected const COLUMNS = [
        'ID CUSTOMER' => 'kode_bt',
        'NAMA CUSTOMER' => 'dealer_name',
        'ALIAS' => 'alias',
        'ALAMAT' => 'address',
        'ID GS' => 'id_realme',
        'NAMA GS' => 'gs_name',
        'STATUS GS' => 'status_gs',
        'NPWP/NIK' => 'npwp',
        'NAMA NPWP/NIK' => 'npwp_name',
        'REFFNOTE' => 'reffnote',
        'JENIS NPWP' => 'npwp_type',
        'TELEMARKETING' => 'telemarketing',
        'NO. WA TELEMARKETING' => 'telemarketing_phone',
        'BIG AREA' => 'region',
        'SALES AREA' => 'sales_person',
    ];

    /**
     * Replace (or update) Master Dealer with the stores in the file.
     *
     * @return array{
     *     imported: int,
     *     per_area: array<string, int>,
     *     skipped: array<int, array{row: int, kode_bt: ?string, dealer_name: ?string, reason: string, values: array<int, mixed>}>,
     *     duplicates: array<string, int>,
     *     normalized_kode_bt: int,
     *     kept_original_kode_bt: array<int, string>,
     *     regions_created: array<int, string>,
     *     header: array<int, string>
     * }
     */
    public function import(string $path, bool $replaceExisting = true): array
    {
        // Baris header dicek dulu, supaya file lain (mis. Draft Program puluhan MB) ditolak tanpa memuat seluruh isinya
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row === 1;
            }
        });
        $this->mapColumns(array_map(fn ($cell) => trim((string) $cell), $reader->load($path)->getActiveSheet()->toArray(null, false, false, false)[0] ?? []));

        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $header = array_map(fn ($cell) => trim((string) $cell), array_shift($rows) ?? []);
        $columns = $this->mapColumns($header);

        $dealers = [];
        $skipped = [];
        $seen = [];
        $duplicates = [];
        $normalized = 0;
        $keptOriginal = [];

        // Kode BT asli di file: "BTO…" tidak diubah jadi "BT0…" kalau kode itu sudah dipakai toko lain
        $rawCodes = array_flip(array_map(
            fn ($row) => strtoupper(trim((string) ($row[$columns['kode_bt']] ?? ''))),
            $rows
        ));

        foreach ($rows as $index => $row) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $values = [];
            foreach ($columns as $attribute => $columnIndex) {
                $value = trim(preg_replace('/\s+/', ' ', (string) ($row[$columnIndex] ?? '')));
                $values[$attribute] = ($value === '' || $value === '-') ? null : $value;
            }

            $rowNumber = $index + 2;
            $kodeBt = Dealer::normalizeKodeBt($values['kode_bt']);
            if ($kodeBt === null) {
                $skipped[] = $this->skippedRow($rowNumber, $values, 'ID Customer kosong', $row);

                continue;
            }
            $rawKodeBt = strtoupper((string) $values['kode_bt']);
            if ($kodeBt !== $rawKodeBt && isset($rawCodes[$kodeBt])) {
                $keptOriginal[] = "{$rawKodeBt} ({$values['dealer_name']}): {$kodeBt} sudah dipakai toko lain";
                $kodeBt = $rawKodeBt;
            } elseif ($kodeBt !== $rawKodeBt) {
                $normalized++;
            }

            $bigArea = $values['region'] !== null ? strtoupper($values['region']) : null;
            $area = self::BIG_AREA_TO_AREA[$bigArea] ?? self::REFFNOTE_TO_AREA[strtoupper((string) $values['reffnote'])] ?? null;
            if ($area === null) {
                $skipped[] = $this->skippedRow($rowNumber, $values, 'Big Area & Reffnote kosong / tidak dikenal', $row);

                continue;
            }

            if (isset($seen[$kodeBt])) {
                $duplicates[$kodeBt] = ($duplicates[$kodeBt] ?? 1) + 1;
            }
            $seen[$kodeBt] = true;

            // Baris terakhir untuk kode BT yang sama yang dipakai
            $dealers[$kodeBt] = [
                'area' => $area->value,
                'kode_bt' => $kodeBt,
                'dealer_name' => $values['dealer_name'] ?? $kodeBt,
                'alias' => $values['alias'],
                'address' => $values['address'],
                'id_realme' => $values['id_realme'] !== null ? strtoupper($values['id_realme']) : null,
                'gs_name' => $values['gs_name'],
                'npwp' => $values['npwp'],
                'npwp_name' => $values['npwp_name'],
                'npwp_type' => $values['npwp_type'] !== null ? strtoupper($values['npwp_type']) : null,
                'reffnote' => $values['reffnote'],
                'telemarketing' => $values['telemarketing'],
                'telemarketing_phone' => $values['telemarketing_phone'],
                'sales_person' => $values['sales_person'],
                'region' => isset(self::BIG_AREA_TO_AREA[$bigArea]) ? $bigArea : null,
                'is_active' => strtoupper((string) $values['status_gs']) !== 'DISABLED',
            ];
        }

        $regionsCreated = [];

        DB::transaction(function () use ($dealers, $replaceExisting, &$regionsCreated) {
            // Agreement & email toko tidak ada di file CV TOP: saat "Ganti semua", simpan dulu lalu pasang lagi ke kode BT yang sama
            $kept = collect();
            if ($replaceExisting) {
                $kept = Dealer::withoutGlobalScopes()
                    ->where(fn ($q) => $q->whereNotNull('agreement_url')->orWhereNotNull('email'))
                    ->get(['kode_bt', 'agreement_url', 'agreement_updated_at', 'email']);
                Dealer::withoutGlobalScopes()->delete();
            }

            $now = now();
            foreach (array_chunk(array_values($dealers), 500) as $chunk) {
                Dealer::withoutGlobalScopes()->upsert(
                    array_map(fn (array $dealer) => $dealer + ['created_at' => $now, 'updated_at' => $now], $chunk),
                    ['kode_bt'],
                    [...array_diff(array_keys($chunk[0]), ['kode_bt']), 'updated_at']
                );
            }

            foreach ($kept as $dealer) {
                Dealer::withoutGlobalScopes()->where('kode_bt', $dealer->kode_bt)->update([
                    'agreement_url' => $dealer->agreement_url,
                    'agreement_updated_at' => $dealer->agreement_updated_at,
                    'email' => $dealer->email,
                ]);
            }

            foreach (collect($dealers)->filter(fn (array $dealer) => $dealer['region'] !== null)->map(fn (array $dealer) => [$dealer['area'], $dealer['region']])->unique() as [$area, $region]) {
                $created = Region::withoutGlobalScopes()->firstOrCreate(['area' => $area, 'name' => $region], ['is_active' => true]);
                if ($created->wasRecentlyCreated) {
                    $regionsCreated[] = Area::from($area)->label().': '.$region;
                }
            }
        });

        return [
            'imported' => count($dealers),
            'per_area' => collect($dealers)->countBy('area')->all(),
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'normalized_kode_bt' => $normalized,
            'kept_original_kode_bt' => $keptOriginal,
            'regions_created' => $regionsCreated,
            'header' => $header,
        ];
    }

    /**
     * Where the rows skipped by the last import are saved (see writeSkippedRows).
     */
    public function skippedRowsPath(): string
    {
        return storage_path('app/dealer-import/dealer-belum-diimport.xlsx');
    }

    /**
     * Save skipped rows (with the reason) to an Excel file so they can be completed and re-imported.
     *
     * @param  array<int, array{row: int, reason: string, values: array<int, mixed>}>  $skipped
     * @param  array<int, string>  $header
     */
    public function writeSkippedRows(array $skipped, array $header, string $path): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Belum Diimport');
        $sheet->fromArray([['Baris Excel', 'Alasan', ...$header]]);

        foreach ($skipped as $index => $item) {
            $sheet->fromArray([[$item['row'], $item['reason'], ...$item['values']]], null, 'A'.($index + 2), true);
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    protected function mapColumns(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $name) {
            $attribute = self::COLUMNS[strtoupper($name)] ?? null;
            // "Nama Customer" muncul dua kali di file; yang pertama dipakai
            if ($attribute !== null && ! isset($columns[$attribute])) {
                $columns[$attribute] = $index;
            }
        }

        foreach (['kode_bt', 'dealer_name'] as $required) {
            if (! isset($columns[$required])) {
                throw new Exception('Kolom "'.array_search($required, self::COLUMNS, true).'" tidak ditemukan di baris header file.');
            }
        }

        return $columns + array_fill_keys(array_diff(array_values(self::COLUMNS), array_keys($columns)), PHP_INT_MAX);
    }

    /**
     * @param  array<string, ?string>  $values
     * @param  array<int, mixed>  $row
     * @return array{row: int, kode_bt: ?string, dealer_name: ?string, reason: string, values: array<int, mixed>}
     */
    protected function skippedRow(int $rowNumber, array $values, string $reason, array $row): array
    {
        return [
            'row' => $rowNumber,
            'kode_bt' => $values['kode_bt'],
            'dealer_name' => $values['dealer_name'],
            'reason' => $reason,
            'values' => $row,
        ];
    }
}

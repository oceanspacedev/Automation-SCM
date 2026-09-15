<?php

namespace App\Services;

use App\Models\Draft;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class DraftImportService
{
    protected InvoiceCalculator $calculator;

    public function __construct(InvoiceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Standard positional mapping fallback (27 columns).
     */
    protected array $standardOrder = [
        0 => 'no',
        1 => 'region',
        2 => 'rsm',
        3 => 'dealer_code',
        4 => 'kode_bt',
        5 => 'customer_name',
        6 => 'dealer_name',
        7 => 'real_qty',
        8 => 'npwp',
        9 => 'npwp_name',
        10 => 'npwp_type',
        11 => 'pph_type',
        12 => 'support_amount',
        13 => 'dpp',
        14 => 'dpp_lain',
        15 => 'ppn',
        16 => 'pph',
        17 => 'netpay',
        18 => 'item_code',
        19 => 'item_name',
        20 => 'address',
        21 => 'email',
        22 => 'whatsapp',
        23 => 'program_name',
        24 => 'program_period',
        25 => 'cn_number',
        26 => 'invoice_date',
        27 => 'ref_note',
        28 => 'invoice_type',
    ];

    /**
     * Dictionary of column aliases to target fields.
     */
    protected array $aliases = [
        'no' => ['no', 'no.', 'nomor', 'num', 'no_'],
        'region' => ['region', 'wilayah', 'reg'],
        'rsm' => ['rsm', 'nama rsm'],
        'dealer_code' => ['dealer code', 'dealer_code', 'dealercode', 'kode dealer', 'kd dealer', 'dealer'],
        'kode_bt' => ['kode bt', 'kode_bt', 'kodebt', 'bt'],
        'customer_name' => ['nama cust by csa', 'nama cust', 'customer by csa', 'customer name', 'nama customer', 'customer', 'cust by csa', 'cust'],
        'dealer_name' => ['dealer name', 'dealer_name', 'dealername', 'nama dealer'],
        'real_qty' => ['realqty', 'real qty', 'real_qty', 'qty', 'real quantity'],
        'npwp' => ['npwp', 'no npwp', 'nomor npwp'],
        'npwp_name' => ['nama npwp', 'nama_npwp', 'npwp name', 'nama di npwp'],
        'npwp_type' => ['jenis npwp', 'jenis_npwp', 'npwp type', 'tipe npwp'],
        'pph_type' => ['jenis pph', 'jenis_pph', 'pph type', 'tipe pph', 'pph rate'],
        'support_amount' => ['support amount', 'support_amount', 'supportamount', 'amount support', 'support'],
        'dpp' => ['dpp', 'dasar pengenaan pajak'],
        'dpp_lain' => ['dpp lain', 'dpp_lain', 'dpplain'],
        'ppn' => ['ppn', 'pajak pertambahan nilai'],
        'pph' => ['pph', 'pajak penghasilan'],
        'netpay' => ['netpay', 'net pay', 'net_pay', 'total netpay'],
        'item_code' => ['kode item', 'kode_item', 'kodeitem', 'item code', 'kd item'],
        'item_name' => ['nama item', 'nama_item', 'namaitem', 'item name', 'nama barang'],
        'address' => ['alamat', 'address', 'alamat dealer', 'alamat customer'],
        'email' => ['email', 'email dealer', 'email address', 'alamat email', 'e-mail', 'email cust', 'email customer'],
        'whatsapp' => ['whatsapp', 'no whatsapp', 'no_whatsapp', 'nomor whatsapp', 'wa', 'no wa', 'nomor wa', 'telepon', 'no telp', 'no hp', 'phone', 'phone number', 'whatsapp dealer', 'wa dealer', 'wa customer', 'whatsapp customer', 'no telepon'],
        'program_name' => ['nama program', 'nama_program', 'program name', 'program'],
        'program_period' => ['periode program', 'periode_program', 'program period', 'periode'],
        'cn_number' => ['no cn', 'no_cn', 'cn number', 'nomor cn', 'cn'],
        'invoice_date' => ['tanggal inv', 'tanggal_inv', 'tgl inv', 'invoice date', 'tgl invoice', 'tanggal invoice', 'tanggal'],
        'ref_note' => ['refnote', 'ref note', 'ref_note', 'reference note', 'catatan'],
        'invoice_type' => ['dsa/nps fl', 'dsa / nps fl', 'dsa/npsfl', 'dsa / nps', 'dsa/nps', 'dsa / nps fl.', 'dsa', 'nps fl', 'invoice type', 'tipe invoice'],
    ];

    /**
     * Import draft Excel file.
     */
    public function import(UploadedFile|string $file, string $defaultInvoiceType = 'NPS FL'): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $allRows = $worksheet->toArray(null, true, false, false);

        if (empty($allRows) || count($allRows) < 1) {
            return [
                'total_rows' => 0,
                'success' => 0,
                'failed' => 0,
                'warning' => 0,
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'File',
                        'value' => '',
                        'error' => 'File Excel kosong atau tidak memiliki baris data.',
                    ],
                ],
            ];
        }

        // 1. Detect header row by scanning first 15 rows
        $detected = $this->detectHeaderRow($allRows);
        $headerRowIndex = $detected['index'];
        $columnMapping = $detected['mapping'];

        // If no header found with at least 3 matches, use positional fallback
        if (count($columnMapping) < 3) {
            $headerRowIndex = 0;
            $columnMapping = $this->standardOrder;
        }

        // Slice rows to only get data rows (rows strictly after the detected header row)
        $dataRows = array_slice($allRows, $headerRowIndex + 1);

        if (empty($dataRows)) {
            return [
                'total_rows' => 0,
                'success' => 0,
                'failed' => 0,
                'warning' => 0,
                'errors' => [
                    [
                        'row' => $headerRowIndex + 1,
                        'field' => 'Data',
                        'value' => '',
                        'error' => 'Tidak ada baris data setelah header.',
                    ],
                ],
            ];
        }

        $totalRows = 0;
        $successCount = 0;
        $failedCount = 0;
        $warningCount = 0;
        $errors = [];

        foreach ($dataRows as $rowIndex => $rowData) {
            $excelRowNumber = $headerRowIndex + 1 + $rowIndex + 1; // 1-indexed Excel row

            // Skip entirely empty rows
            if (empty(array_filter($rowData, fn ($v) => ! is_null($v) && trim((string) $v) !== ''))) {
                continue;
            }

            // Also skip if the row is just column numbering like "1, 2, 3, 4..."
            if ($this->isNumberingRow($rowData)) {
                continue;
            }

            $data = [];

            foreach ($columnMapping as $colIndex => $field) {
                $data[$field] = $rowData[$colIndex] ?? null;
            }

            // Clean and parse data
            $cleanData = $this->sanitizeRowData($data);

            // Skip trailing template rows, grand totals, or rows without dealer / customer
            $hasDealerIdentifier = ! empty($cleanData['dealer_code']) || ! empty($cleanData['dealer_name']) || ! empty($cleanData['customer_name']);
            if (! $hasDealerIdentifier) {
                continue;
            }

            $totalRows++;

            // Clean item_code if it starts with numbering like "1. 38000016"
            if (! empty($cleanData['item_code'])) {
                $cleanData['item_code'] = preg_replace('/^\d+\.\s*/', '', $cleanData['item_code']);
            }

            // Validation & Fallback: DSA/NPS FL/REGULAR
            $rowErrors = [];
            $rawType = strtoupper(trim((string) ($cleanData['invoice_type'] ?? '')));

            if ($rawType === '' || is_null($cleanData['invoice_type'])) {
                // Empty in Excel: gracefully fallback to default invoice type
                $normDefault = strtoupper(trim($defaultInvoiceType ?? ''));
                if ($normDefault === 'DSA') {
                    $cleanData['invoice_type'] = 'DSA';
                } elseif (str_contains($normDefault, 'REGUL')) {
                    $cleanData['invoice_type'] = 'REGULAR';
                } else {
                    $cleanData['invoice_type'] = 'NPS FL';
                }
            } elseif (str_contains($rawType, 'DSA')) {
                $cleanData['invoice_type'] = 'DSA';
            } elseif (str_contains($rawType, 'REGUL')) {
                $cleanData['invoice_type'] = 'REGULAR';
            } elseif (str_contains($rawType, 'NPS')) {
                $cleanData['invoice_type'] = 'NPS FL';
            } else {
                $rowErrors[] = [
                    'row' => $excelRowNumber,
                    'field' => 'DSA/NPS FL/REGULAR',
                    'value' => (string) ($cleanData['invoice_type'] ?? ''),
                    'error' => "Nilai tipe invoice harus bernilai 'DSA', 'NPS FL', atau 'REGULAR'.",
                ];
            }

            // Calculation validation & comparison
            $calcResult = $this->calculator->calculateFromValues(
                (float) $cleanData['support_amount'],
                $cleanData['pph_type'] ?? null,
                $cleanData['npwp_type'] ?? null
            );

            // Compare system calculation with Excel values
            $comparison = [];
            $hasDiff = false;
            foreach (['dpp', 'dpp_lain', 'ppn', 'pph', 'netpay'] as $field) {
                $excelVal = (float) ($cleanData[$field] ?? 0);
                $systemVal = (float) $calcResult[$field];
                $diff = round(abs($excelVal - $systemVal), 2);
                $isMatch = ($diff < 1.0);

                if (! $isMatch && $excelVal > 0) {
                    $hasDiff = true;
                }

                $comparison[$field] = [
                    'excel' => $excelVal,
                    'system' => $systemVal,
                    'diff' => $diff,
                    'status' => $isMatch ? 'MATCH' : 'DIFFERENT',
                ];
            }

            if ($hasDiff) {
                $warningCount++;
            }

            // Determine status
            if (! empty($rowErrors)) {
                $cleanData['status'] = 'error';
                $failedCount++;
                foreach ($rowErrors as $err) {
                    $errors[] = $err;
                }
            } else {
                $cleanData['status'] = 'ready';
                $successCount++;
            }

            $cleanData['validation_notes'] = [
                'is_matched' => ! $hasDiff,
                'comparison' => $comparison,
                'calculated' => $calcResult,
                'errors' => $rowErrors,
            ];

            // Save to database
            try {
                Draft::create($cleanData);
            } catch (Exception $e) {
                $failedCount++;
                if ($cleanData['status'] === 'ready') {
                    $successCount = max(0, $successCount - 1);
                }
                $errors[] = [
                    'row' => $excelRowNumber,
                    'field' => 'Database',
                    'value' => '',
                    'error' => 'Gagal menyimpan ke database: '.$e->getMessage(),
                ];
            }
        }

        return [
            'total_rows' => $totalRows,
            'success' => $successCount,
            'failed' => $failedCount,
            'warning' => $warningCount,
            'errors' => $errors,
        ];
    }

    /**
     * Dynamically detect which row contains the real table headers.
     */
    protected function detectHeaderRow(array $rows): array
    {
        $bestIndex = 0;
        $bestMapping = [];
        $maxMatches = 0;

        $maxScan = min(count($rows), 15);

        for ($i = 0; $i < $maxScan; $i++) {
            $candidate = $rows[$i];
            $mapping = [];
            $matchCount = 0;

            foreach ($candidate as $colIndex => $cellValue) {
                $normalized = $this->normalizeString((string) $cellValue);
                if (empty($normalized)) {
                    continue;
                }

                foreach ($this->aliases as $field => $aliasList) {
                    if (in_array($normalized, $aliasList, true)) {
                        $mapping[$colIndex] = $field;
                        $matchCount++;
                        break;
                    }
                }
            }

            if ($matchCount > $maxMatches) {
                $maxMatches = $matchCount;
                $bestIndex = $i;
                $bestMapping = $mapping;
            }
        }

        return [
            'index' => $bestIndex,
            'mapping' => $bestMapping,
            'matches' => $maxMatches,
        ];
    }

    /**
     * Check if a row is just Excel column index numbers (e.g., 1, 2, 3, 4...).
     */
    protected function isNumberingRow(array $rowData): bool
    {
        $nonEmpty = array_values(array_filter($rowData, fn ($v) => ! is_null($v) && trim((string) $v) !== ''));
        if (count($nonEmpty) < 3) {
            return false;
        }

        $numericCount = 0;
        foreach ($nonEmpty as $val) {
            if (is_numeric(trim((string) $val))) {
                $numericCount++;
            }
        }

        // If more than 80% are sequential numbers, it's a helper numbering row
        return ($numericCount / count($nonEmpty)) >= 0.8;
    }

    /**
     * Normalize string for header comparison.
     */
    protected function normalizeString(string $val): string
    {
        // Replace non-breaking spaces and line breaks with regular space
        $clean = str_replace(["\xc2\xa0", "\xa0", "\r\n", "\n", "\r", "\t"], ' ', $val);
        $clean = preg_replace('/\s+/', ' ', $clean);

        return strtolower(trim($clean));
    }

    /**
     * Clean and parse values from raw Excel row.
     */
    protected function sanitizeRowData(array $data): array
    {
        $clean = [];

        // String fields
        $stringFields = [
            'no', 'region', 'rsm', 'dealer_code', 'kode_bt', 'customer_name',
            'dealer_name', 'npwp', 'npwp_name', 'npwp_type', 'pph_type',
            'item_code', 'item_name', 'address', 'email', 'whatsapp', 'program_name',
            'program_period', 'cn_number', 'ref_note', 'invoice_type',
        ];

        foreach ($stringFields as $f) {
            $val = $data[$f] ?? null;
            if (! is_null($val)) {
                $trimmed = trim((string) $val);
                if (in_array(strtoupper($trimmed), ['#N/A', '#VALUE!', '#REF!', '#NAME?', 'NULL', 'N/A'])) {
                    $clean[$f] = null;
                } elseif ($f === 'whatsapp') {
                    // Clean phone number: remove spaces, dashes, parentheses
                    $cleanedPhone = preg_replace('/[^\d+]/', '', $trimmed);
                    $clean[$f] = ! empty($cleanedPhone) ? $cleanedPhone : null;
                } else {
                    $clean[$f] = $trimmed;
                }
            } else {
                $clean[$f] = null;
            }
        }

        // Numeric fields
        $numericFields = [
            'real_qty', 'support_amount', 'dpp', 'dpp_lain', 'ppn', 'pph', 'netpay',
        ];

        foreach ($numericFields as $f) {
            $val = $data[$f] ?? 0;
            if (is_string($val)) {
                $val = preg_replace('/[^\d.-]/', '', $val);
            }
            $clean[$f] = (float) ($val ?: 0);
        }

        // Invoice Date handling
        $dateVal = $data['invoice_date'] ?? null;
        if (! empty($dateVal)) {
            if (is_numeric($dateVal)) {
                try {
                    $clean['invoice_date'] = Carbon::instance(ExcelDate::excelToDateTimeObject($dateVal))->format('Y-m-d');
                } catch (Exception) {
                    $clean['invoice_date'] = (string) $dateVal;
                }
            } else {
                try {
                    $clean['invoice_date'] = Carbon::parse($dateVal)->format('Y-m-d');
                } catch (Exception) {
                    $clean['invoice_date'] = (string) $dateVal;
                }
            }
        } else {
            $clean['invoice_date'] = null;
        }

        return $clean;
    }
}

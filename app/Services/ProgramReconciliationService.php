<?php

namespace App\Services;

use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProgramReconciliationService
{
    /**
     * Tolerance for financial difference in Rupiah (due to tax rounding / minor differences).
     */
    public const FINANCIAL_TOLERANCE = 10.0;

    /**
     * Normalize dealer name for comparison.
     * Strips corporate prefixes/suffixes (PT, CV, UD, TOKO, STORE, etc.) and punctuation.
     */
    public function normalizeDealerName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        $clean = strtoupper(trim($name));

        // Remove common legal prefixes/suffixes
        $clean = preg_replace('/\b(PT|CV|UD|PD|TB|TOKO|SHOP|STORE|CELL|CELLULAR|SELULER|KOMUNIKA|PHONE)\b\.?/i', ' ', $clean);

        // Remove punctuation and special characters
        $clean = preg_replace('/[^A-Z0-9\s]/', ' ', $clean);

        // Collapse multiple spaces
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        return $clean;
    }

    /**
     * Normalize program name for comparison.
     */
    public function normalizeProgramName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        $clean = strtoupper(trim($name));
        $clean = preg_replace('/[^A-Z0-9\s]/', ' ', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        return $clean;
    }

    /**
     * Check if a URL is a valid Google Drive or HTTP link.
     */
    public function isValidUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $trimmed = trim($url);

        return str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://');
    }

    /**
     * Normalize region string for comparison (e.g. "BIG KARAWANG 2" -> "KARAWANG").
     */
    public function normalizeRegion(?string $region): string
    {
        if (empty($region)) {
            return '';
        }

        $norm = strtoupper(trim((string) $region));
        $norm = str_replace(['BIG', 'AREA', 'BIGREG', 'REGION'], '', $norm);
        $norm = preg_replace('/[0-9_\-\.\/\\\\]+/', '', $norm);

        return trim(preg_replace('/\s+/', ' ', $norm));
    }

    /**
     * Check if submission region matches DataProgram region.
     * If region is strictly different, returns false.
     */
    public function isRegionMatch(?string $subRegion, ?string $dpRegion, ?string $dpBigRegion = null): bool
    {
        $normSub = $this->normalizeRegion($subRegion);
        if (empty($normSub)) {
            return true;
        }

        $normDp = $this->normalizeRegion($dpRegion);
        $normBig = $this->normalizeRegion($dpBigRegion);

        if (empty($normDp) && empty($normBig)) {
            return true;
        }

        if (! empty($normDp) && (str_contains($normDp, $normSub) || str_contains($normSub, $normDp))) {
            return true;
        }

        if (! empty($normBig) && (str_contains($normBig, $normSub) || str_contains($normSub, $normBig))) {
            return true;
        }

        return false;
    }

    /**
     * Check if ID Real (Form Program) matches Kode BT (Data Program).
     * Returns true if match, false if both exist but differ, or null if either is missing.
     */
    public function isBtMatch(?string $idReal, ?string $kodeBt): ?bool
    {
        $cleanReal = strtoupper(trim(preg_replace('/[^A-Za-z0-9]/', '', (string) $idReal)));
        $cleanBt = strtoupper(trim(preg_replace('/[^A-Za-z0-9]/', '', (string) $kodeBt)));

        if (empty($cleanReal) || empty($cleanBt) || $cleanReal === '-' || $cleanBt === '-') {
            return null;
        }

        return $cleanReal === $cleanBt;
    }

    /**
     * Determine if a DataProgram record is PKP (Pengusaha Kena Pajak).
     * PKP entities: BADAN, PRIBADI PKP, PKP, or any record where PPN > 0.
     * NON-PKP entities: ORANG PRIBADI, PRIBADI, NON, BADAN NON, BADAN NON PKP (with PPN == 0).
     */
    public function isPkp(DataProgram $dataProgram, ?ProgramSubmission $submission = null): bool
    {
        $wp = strtoupper(trim((string) ($dataProgram->wajib_pajak ?? '')));

        // 1. Explicit NON-PKP overrides (e.g. BADAN NON, BADAN NON PKP, NON)
        if (str_contains($wp, 'NON')) {
            return false;
        }

        // 2. Explicit PKP or BADAN (user rule: "BADAN SAMA PRIBADI PKP")
        if (str_contains($wp, 'PKP') || $wp === 'BADAN') {
            return true;
        }

        // 3. PPN presence indicates PKP
        if ((float) ($dataProgram->ppn ?? 0) > 0) {
            return true;
        }

        if ($submission && (float) ($submission->ppn ?? 0) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a ProgramSubmission is PKP by checking its PPN or matching DataProgram.
     */
    public function isPkpFromSubmission(ProgramSubmission $submission): bool
    {
        if ((float) ($submission->ppn ?? 0) > 0) {
            return true;
        }

        $idReal = trim((string) ($submission->id_real ?? ''));
        $dp = null;

        if (! empty($idReal) && $idReal !== '-') {
            $dp = DataProgram::where('kode_bt', $idReal)->first();
        }

        if (! $dp && ! empty($submission->dealer_name)) {
            $dp = DataProgram::where('dealer_name', 'like', "%{$submission->dealer_name}%")->first();
        }

        if ($dp) {
            return $this->isPkp($dp, $submission);
        }

        return false;
    }

    /**
     * Evaluate strict compatibility and calculate score between a DataProgram and a ProgramSubmission.
     * Strictly requires 4 core criteria: Region, ID Real / Kode BT, Dealer Name, and Program Name.
     *
     * Returns null if candidate fails any of the 4 strict criteria.
     * Returns integer score (>= 70) if candidate passes.
     */
    public function evaluateMatchScore(DataProgram $dataProgram, ProgramSubmission $sub): ?int
    {
        // 1. Strict Region Check: Region MUST match. If different, immediately disqualify!
        if (! $this->isRegionMatch($sub->region, $dataProgram->region, $dataProgram->big_region)) {
            return null;
        }

        // 2. Strict ID Real / Kode BT Check: If both exist and differ, immediately disqualify!
        $btMatch = $this->isBtMatch($sub->id_real, $dataProgram->kode_bt);
        if ($btMatch === false) {
            return null;
        }

        $score = 0;
        if ($btMatch === true) {
            $score += 50;
        }

        // 3. Strict Dealer Name Check: Dealer name MUST match.
        // Even if Kode BT matches, different dealers (e.g. M2 Cell vs Kosambi) are strictly disqualified!
        $dpDealerNorm = $this->normalizeDealerName($dataProgram->dealer_name);
        $subDealerNorm = $this->normalizeDealerName($sub->dealer_name);

        if (empty($dpDealerNorm) || empty($subDealerNorm)) {
            return null;
        }

        $dealerMatched = false;
        if ($dpDealerNorm === $subDealerNorm) {
            $score += 50;
            $dealerMatched = true;
        } elseif (str_contains($subDealerNorm, $dpDealerNorm) || str_contains($dpDealerNorm, $subDealerNorm)) {
            $score += 35;
            $dealerMatched = true;
        } else {
            $dpTokens = array_filter(explode(' ', $dpDealerNorm), fn ($t) => strlen($t) >= 3);
            $subTokens = array_filter(explode(' ', $subDealerNorm), fn ($t) => strlen($t) >= 3);
            $overlap = array_intersect($dpTokens, $subTokens);
            if (count($overlap) >= 1 && count($overlap) / max(1, count($dpTokens)) >= 0.5) {
                $score += min(30, count($overlap) * 15);
                $dealerMatched = true;
            }
        }

        if (! $dealerMatched) {
            return null;
        }

        // 4. Strict Program Name Check: Program name MUST match.
        $dpProgNorm = $this->normalizeProgramName($dataProgram->program_name);
        $subProgNorm = $this->normalizeProgramName($sub->program_name);

        if (! empty($dpProgNorm) && ! empty($subProgNorm)) {
            $progMatched = false;
            if ($dpProgNorm === $subProgNorm) {
                $score += 40;
                $progMatched = true;
            } elseif (str_contains($subProgNorm, $dpProgNorm) || str_contains($dpProgNorm, $subProgNorm)) {
                $score += 25;
                $progMatched = true;
            } else {
                $noise = [
                    'PROGRAM', 'PERIODE', 'BULAN', 'SERIES', 'TAHUN', 'YEAR', 'DATE',
                    'PRICE', 'PROTECTION', 'SELL', 'OUT', 'CASHBACK', 'COMMISSION', 'ALL', 'TYPE',
                    'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS',
                    'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER',
                    'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC',
                ];
                $filterDistinctive = function ($norm) use ($noise) {
                    $tokens = explode(' ', $norm);

                    return array_values(array_filter($tokens, function ($t) use ($noise) {
                        if (strlen($t) < 2) {
                            return false;
                        }
                        if (in_array($t, $noise, true)) {
                            return false;
                        }
                        if (preg_match('/^202[0-9]$/', $t)) {
                            return false;
                        }

                        return true;
                    }));
                };

                $t1 = $filterDistinctive($dpProgNorm);
                $t2 = $filterDistinctive($subProgNorm);

                $overlap = array_intersect($t1, $t2);
                if (! empty($t1) && ! empty($t2)) {
                    $minCount = min(count($t1), count($t2));
                    if (count($overlap) >= 1 && (count($overlap) / $minCount >= 0.5)) {
                        $score += min(25, count($overlap) * 10);
                        $progMatched = true;
                    }
                }
            }

            if (! $progMatched) {
                return null;
            }
        }

        // 5. Financial Amount Match (Net Pay or DPP)
        $dpNet = (float) ($dataProgram->net_pay ?? 0);
        $subNet = (float) ($sub->net_pay ?? 0);
        $dpDpp = (float) ($dataProgram->dpp ?? 0);
        $subDpp = (float) ($sub->dpp ?? 0);

        if ($dpNet > 0 && $subNet > 0 && abs($dpNet - $subNet) <= self::FINANCIAL_TOLERANCE) {
            $score += 45;
        } elseif ($dpDpp > 0 && $subDpp > 0 && abs($dpDpp - $subDpp) <= self::FINANCIAL_TOLERANCE) {
            $score += 40;
        } elseif ($dpNet > 0 && $subNet > 0) {
            $diff = abs($dpNet - $subNet);
            if ($diff > 1000) {
                $score -= 10;
            }
        }

        // 6. Sales Person Match (e.g. Dimas Aji Pratama)
        $dpSalesNorm = $this->normalizeDealerName($dataProgram->sales_person);
        $subSalesNorm = $this->normalizeDealerName($sub->sales_name);
        if (! empty($dpSalesNorm) && ! empty($subSalesNorm)) {
            if ($dpSalesNorm === $subSalesNorm) {
                $score += 25;
            } elseif (str_contains($subSalesNorm, $dpSalesNorm) || str_contains($dpSalesNorm, $subSalesNorm)) {
                $score += 15;
            }
        }

        // 7. Document Availability & Completeness
        // Submissions that provide actual documents (especially Faktur Pajak for PKP) must score higher
        $hasCn = $this->isValidUrl($sub->credit_note_url);
        $hasAgr = $this->isValidUrl($sub->agreement_url);
        $hasFaktur = $this->isValidUrl($sub->tax_invoice_url);

        if ($hasCn) {
            $score += 10;
        }
        if ($hasAgr) {
            $score += 10;
        }
        if ($hasFaktur) {
            $score += 15;
        }
        if (! empty(trim((string) ($sub->no_faktur ?? '')))) {
            $score += 10;
        }

        $isPkp = $this->isPkp($dataProgram, $sub);
        if ($isPkp && $hasCn && $hasAgr && $hasFaktur) {
            $score += 30; // Complete documents for PKP
        } elseif (! $isPkp && $hasCn && $hasAgr) {
            $score += 25; // Complete documents for Non-PKP
        }

        // Candidate must meet strict confidence score threshold >= 70
        if ($score < 70) {
            return null;
        }

        return $score;
    }

    /**
     * Find best candidate ProgramSubmission for a given DataProgram.
     * Strictly requires Region, Kode BT/ID Real, Dealer Name, and Program Name compatibility.
     */
    public function findMatchingSubmission(DataProgram $dataProgram, ?Collection $preloadedSubmissions = null): ?ProgramSubmission
    {
        $dpDealerNorm = $this->normalizeDealerName($dataProgram->dealer_name);

        if (empty($dpDealerNorm)) {
            return null;
        }

        $candidates = $preloadedSubmissions;

        if ($candidates === null) {
            $query = ProgramSubmission::query();
            $tokens = array_filter(explode(' ', $dpDealerNorm), fn ($t) => strlen($t) >= 3);
            $kodeBt = trim((string) ($dataProgram->kode_bt ?? ''));

            $query->where(function ($q) use ($kodeBt, $dataProgram, $tokens) {
                if (! empty($kodeBt) && $kodeBt !== '-') {
                    $q->where('id_real', $kodeBt);
                }
                $q->orWhere('dealer_name', 'like', "%{$dataProgram->dealer_name}%");
                foreach ($tokens as $token) {
                    $q->orWhere('dealer_name', 'like', "%{$token}%");
                }
            });

            $candidates = $query->orderByDesc('id')->get();
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        $bestCandidate = null;
        $bestScore = 0;

        foreach ($candidates as $sub) {
            $score = $this->evaluateMatchScore($dataProgram, $sub);
            if ($score === null) {
                continue;
            }

            if ($bestCandidate === null || $score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = $sub;
            } elseif ($score === $bestScore) {
                // Tie breaker: prioritize submission with more valid documents (especially Faktur for PKP)
                $currentDocs = ($this->isValidUrl($sub->credit_note_url) ? 1 : 0)
                    + ($this->isValidUrl($sub->agreement_url) ? 1 : 0)
                    + ($this->isValidUrl($sub->tax_invoice_url) ? 1 : 0);
                $bestDocs = ($this->isValidUrl($bestCandidate->credit_note_url) ? 1 : 0)
                    + ($this->isValidUrl($bestCandidate->agreement_url) ? 1 : 0)
                    + ($this->isValidUrl($bestCandidate->tax_invoice_url) ? 1 : 0);

                if ($currentDocs > $bestDocs) {
                    $bestScore = $score;
                    $bestCandidate = $sub;
                }
            }
        }

        return $bestCandidate;
    }

    /**
     * Reconcile a single DataProgram record with a matching ProgramSubmission.
     *
     * @return array{
     *     success: bool,
     *     data_program_id: int,
     *     submission_id: ?int,
     *     status: string,
     *     cek_dokumen: string,
     *     keterangan: string,
     *     drive_transferred: array<string, bool>,
     *     selisih: float,
     *     is_financial_match: bool
     * }
     */
    public function reconcileSingle(DataProgram $dataProgram, ?ProgramSubmission $submission = null, bool $force = false): array
    {
        if ($submission === null) {
            $submission = $this->findMatchingSubmission($dataProgram);
        }

        if (! $submission) {
            return [
                'success' => false,
                'data_program_id' => $dataProgram->id,
                'submission_id' => null,
                'status' => $dataProgram->status_potong_purchase ?? 'BELUM BISA POTONG',
                'cek_dokumen' => $dataProgram->cek_dokumen ?? 'BELUM MATCH FORM',
                'keterangan' => 'Belum ditemukan data matching di Form Program.',
                'drive_transferred' => ['cn' => false, 'agrement' => false, 'cek_fp' => false],
                'selisih' => 0.0,
                'is_financial_match' => false,
            ];
        }

        // 1. Evaluate Document Availability & Smart AI Document Mapping
        $rawCn = trim((string) $submission->credit_note_url);
        $rawAgr = trim((string) $submission->agreement_url);
        $rawFaktur = trim((string) $submission->tax_invoice_url);

        $docValidation = (array) ($submission->doc_validation ?? []);
        $agrValidation = $docValidation['agr'] ?? [];
        $taxValidation = $docValidation['faktur'] ?? ($docValidation['tax'] ?? []);

        $actualCn = $this->isValidUrl($rawCn) ? $rawCn : null;
        $actualAgr = $this->isValidUrl($rawAgr) ? $rawAgr : null;
        $actualFaktur = $this->isValidUrl($rawFaktur) ? $rawFaktur : null;

        // If slot Agreement actually contains Faktur Pajak and Faktur slot is empty, smart-route:
        if (! $actualFaktur && ($agrValidation['actual_type'] ?? '') === 'faktur' && $actualAgr) {
            $actualFaktur = $actualAgr;
        }

        // If slot Faktur actually contains Agreement and Agr slot is empty, smart-route:
        if (! $actualAgr && ($taxValidation['actual_type'] ?? '') === 'agreement' && $actualFaktur) {
            $actualAgr = $actualFaktur;
        }

        $hasCn = ! empty($actualCn) || $this->isValidUrl($dataProgram->cn);
        $hasAgr = ! empty($actualAgr) || $this->isValidUrl($dataProgram->agrement);
        $hasFaktur = ! empty($actualFaktur) || $this->isValidUrl($dataProgram->cek_fp) || (! empty($dataProgram->no_faktur_pajak) && $dataProgram->no_faktur_pajak !== '-');

        // 2. Transfer Google Drive URLs & Tax Invoice to DataProgram (if empty, '-' or forced)
        $transferred = [
            'cn' => false,
            'agrement' => false,
            'cek_fp' => false,
        ];

        if ($actualCn && ($force || empty($dataProgram->cn) || $dataProgram->cn === '-')) {
            $dataProgram->cn = $actualCn;
            $transferred['cn'] = true;
        }

        if ($actualAgr && ($force || empty($dataProgram->agrement) || $dataProgram->agrement === '-')) {
            $dataProgram->agrement = $actualAgr;
            $transferred['agrement'] = true;
        }

        if ($actualFaktur && ($force || empty($dataProgram->cek_fp) || $dataProgram->cek_fp === '-')) {
            $dataProgram->cek_fp = $actualFaktur;
            $transferred['cek_fp'] = true;
        }

        if (! empty($submission->no_faktur) && ($force || empty($dataProgram->no_faktur_pajak) || $dataProgram->no_faktur_pajak === '-')) {
            $dataProgram->no_faktur_pajak = trim((string) $submission->no_faktur);
            if (empty($dataProgram->ket_faktur_pajak) || $dataProgram->ket_faktur_pajak === '-') {
                $dataProgram->ket_faktur_pajak = 'FP SESUAI';
            }
        }

        // 3. Evaluate Financial Match
        $dpNet = (float) ($dataProgram->net_pay ?? 0);
        $subNet = (float) ($submission->net_pay ?? 0);
        $dpDpp = (float) ($dataProgram->dpp ?? 0);
        $subDpp = (float) ($submission->dpp ?? 0);

        $hasFinancialMatch = false;
        $selisih = 0.0;

        if ($dpNet > 0 && $subNet > 0) {
            $selisih = abs($dpNet - $subNet);
            $hasFinancialMatch = $selisih <= self::FINANCIAL_TOLERANCE;
        } elseif ($dpDpp > 0 && $subDpp > 0) {
            $selisih = abs($dpDpp - $subDpp);
            $hasFinancialMatch = $selisih <= self::FINANCIAL_TOLERANCE;
        } elseif ($subNet == 0 && $subDpp == 0) {
            // Submission has not been analyzed by AI yet or has 0 extracted
            $hasFinancialMatch = true; // tentative match based on dealer & program
        }

        $isPkp = $this->isPkp($dataProgram, $submission);

        // 4. Missing Documents Summary based on PKP status
        // PKP (Badan & Pribadi PKP) requires CN, AGR, and FAKTUR.
        // NON-PKP only requires CN and AGR (Faktur Pajak is not required).
        $missingDocs = [];
        if (! $hasCn) {
            $missingDocs[] = 'CN';
        }
        if (! $hasAgr) {
            $missingDocs[] = 'AGR';
        }
        if ($isPkp && ! $hasFaktur) {
            $missingDocs[] = 'FAKTUR';
        }

        $allDocsPresent = count($missingDocs) === 0;

        // 5. Determine Status Potong Purchase & Cek Dokumen
        if ($hasFinancialMatch && $allDocsPresent) {
            $statusPurchase = 'BISA DI POTONG';
            $cekDokumen = 'LENGKAP';
            $keterangan = sprintf(
                'MATCH Form Program #%d (Finansial Sesuai: Net Pay Rp %s, Dokumen Lengkap%s)',
                $submission->id,
                number_format($dataProgram->net_pay, 0, ',', '.'),
                $isPkp ? '' : ' - NON PKP'
            );
        } elseif (! $hasFinancialMatch) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = 'SELISIH NOMINAL';
            $keterangan = sprintf(
                'SELISIH Form Program #%d (Data: Rp %s vs Form: Rp %s, Selisih: Rp %s)',
                $submission->id,
                number_format($dpNet, 0, ',', '.'),
                number_format($subNet, 0, ',', '.'),
                number_format($selisih, 0, ',', '.')
            );
        } else {
            $statusPurchase = 'BELUM BISA POTONG';
            $missingStr = implode(' & ', $missingDocs).' BELUM ADA';
            $cekDokumen = $missingStr;
            $keterangan = sprintf(
                'MATCH Form Program #%d (Finansial Sesuai, Dokumen Belum Lengkap: %s%s)',
                $submission->id,
                $missingStr,
                $isPkp ? ' [PKP]' : ' [NON PKP]'
            );
        }

        // Only update status and cek_dokumen if not already locked to 'SUDAH POTONG' or 'DONE TRANSFER'
        $existingStatus = (string) $dataProgram->status_potong_purchase;
        if (! str_contains($existingStatus, 'SUDAH') && ! str_contains($existingStatus, 'DONE')) {
            if (! empty($submission->status_potong_purchase) && (str_contains((string) $submission->status_potong_purchase, 'SUDAH') || str_contains((string) $submission->status_potong_purchase, 'DONE'))) {
                $dataProgram->status_potong_purchase = $submission->status_potong_purchase;
            } elseif (($submission->status_potong_ar ?? '') === 'DONE') {
                $dataProgram->status_potong_purchase = 'SUDAH POTONG';
            } else {
                $dataProgram->status_potong_purchase = $statusPurchase;
            }
        }

        // Transfer status_potong_ar & tgl_potong_tf from submission if present
        if (! empty($submission->status_potong_ar) && ($force || empty($dataProgram->status_potong_ar) || $dataProgram->status_potong_ar === '-')) {
            $dataProgram->status_potong_ar = trim((string) $submission->status_potong_ar);
        }
        if (! empty($submission->tgl_potong_tf) && ($force || empty($dataProgram->tgl_potong_tf) || $dataProgram->tgl_potong_tf === '-')) {
            $dataProgram->tgl_potong_tf = trim((string) $submission->tgl_potong_tf);
        } elseif (($dataProgram->status_potong_ar === 'DONE' || $dataProgram->status_potong_purchase === 'SUDAH POTONG') && empty($dataProgram->tgl_potong_tf)) {
            $dataProgram->tgl_potong_tf = date('n/j/Y');
        }

        $dataProgram->cek_dokumen = $cekDokumen;
        // Kolom 'keterangan' adalah input manual tim/spreadsheet dan TIDAK BOLEH diisi oleh AI.
        // Catatan hasil rekonsiliasi AI disimpan di kolom 'noted'
        $dataProgram->noted = $keterangan;

        $dataProgram->save();

        return [
            'success' => true,
            'data_program_id' => $dataProgram->id,
            'submission_id' => $submission->id,
            'status' => $dataProgram->status_potong_purchase,
            'cek_dokumen' => $dataProgram->cek_dokumen,
            'keterangan' => $dataProgram->keterangan,
            'noted' => $dataProgram->noted,
            'ai_note' => $keterangan,
            'drive_transferred' => $transferred,
            'selisih' => $selisih,
            'is_financial_match' => $hasFinancialMatch,
        ];
    }

    /**
     * Batch reconciliation for DataProgram records.
     *
     * @param  array{
     *     limit?: int,
     *     year?: string,
     *     force?: bool,
     *     only_unreconciled?: bool,
     *     search?: string,
     *     program?: string
     * }  $options
     * @return array{
     *     total_evaluated: int,
     *     matched_count: int,
     *     bisa_potong_count: int,
     *     belum_bisa_potong_count: int,
     *     selisih_count: int,
     *     drive_transferred_count: int,
     *     results: array
     * }
     */
    public function reconcileBatch(array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 100));
        $force = (bool) ($options['force'] ?? false);
        $onlyUnreconciled = (bool) ($options['only_unreconciled'] ?? false);
        $year = (string) ($options['year'] ?? '2026');

        $query = DataProgram::query();

        if ($year) {
            $query->where(function ($q) use ($year) {
                $q->where('periode', 'like', "%{$year}%")
                    ->orWhere('program_name', 'like', "%{$year}%")
                    ->orWhere('created_at', 'like', "{$year}%");
            });
        }

        if ($onlyUnreconciled) {
            $query->where(function ($q) {
                $q->whereNull('status_potong_purchase')
                    ->orWhere('status_potong_purchase', '')
                    ->orWhere('status_potong_purchase', '-')
                    ->orWhereNull('cn')
                    ->orWhere('cn', '')
                    ->orWhere('cn', '-');
            });
        }

        if (! empty($options['search'])) {
            $search = trim($options['search']);
            $query->where(function ($q) use ($search) {
                $q->where('dealer_name', 'like', "%{$search}%")
                    ->orWhere('kode_bt', 'like', "%{$search}%")
                    ->orWhere('program_name', 'like', "%{$search}%");
            });
        }

        if (! empty($options['program'])) {
            $query->where('program_name', $options['program']);
        }

        $query->orderByDesc('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $dataPrograms = $query->get();

        if ($dataPrograms->isEmpty()) {
            return [
                'total_evaluated' => 0,
                'matched_count' => 0,
                'bisa_potong_count' => 0,
                'belum_bisa_potong_count' => 0,
                'selisih_count' => 0,
                'drive_transferred_count' => 0,
                'results' => [],
            ];
        }

        // Preload recent/analyzed submissions to avoid N+1 queries
        $subQuery = ProgramSubmission::query();
        if ($year) {
            $subQuery->where(function ($q) use ($year) {
                $q->where('submission_timestamp', 'like', "{$year}%")
                    ->orWhere('created_at', 'like', "{$year}%");
            });
        }
        $submissionsPool = $subQuery->orderByDesc('id')->get();

        $matchedCount = 0;
        $bisaPotongCount = 0;
        $belumBisaPotongCount = 0;
        $selisihCount = 0;
        $driveCount = 0;
        $results = [];
        $rowsToPush = [];

        foreach ($dataPrograms as $dp) {
            try {
                // Auto-retry up to 3 times on transient MySQL deadlocks/locks with 100ms delay
                $res = retry(3, function () use ($dp, $force) {
                    return $this->reconcileSingle($dp, null, $force);
                }, 100);

                if ($res['success']) {
                    $matchedCount++;
                    if ($res['status'] === 'BISA DI POTONG') {
                        $bisaPotongCount++;
                    } else {
                        $belumBisaPotongCount++;
                    }

                    if (! $res['is_financial_match']) {
                        $selisihCount++;
                    }

                    if (in_array(true, $res['drive_transferred'], true)) {
                        $driveCount++;
                    }

                    $results[] = [
                        'dp_id' => $dp->id,
                        'dealer' => $dp->dealer_name,
                        'program' => $dp->program_name,
                        'sub_id' => $res['submission_id'],
                        'status' => $res['status'],
                        'cek_dokumen' => $res['cek_dokumen'],
                        'keterangan' => $res['keterangan'],
                        'drive_transferred' => $res['drive_transferred'],
                    ];

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
            } catch (\Throwable $e) {
                Log::warning("Gagal rekonsiliasi row ID {$dp->id}: ".$e->getMessage());
            }
        }

        $sheetPushResult = null;
        if (! empty($rowsToPush) && ! empty($options['push_to_sheet'])) {
            try {
                $sheetPushResult = app(DataProgramSyncService::class)->pushUpdatesToSpreadsheet($rowsToPush);
            } catch (\Throwable $e) {
                Log::warning('Push to spreadsheet failed: '.$e->getMessage());
            }
        }

        return [
            'total_evaluated' => $dataPrograms->count(),
            'matched_count' => $matchedCount,
            'bisa_potong_count' => $bisaPotongCount,
            'belum_bisa_potong_count' => $belumBisaPotongCount,
            'selisih_count' => $selisihCount,
            'drive_transferred_count' => $driveCount,
            'sheet_push' => $sheetPushResult,
            'results' => $results,
        ];
    }

    /**
     * Auto-reconcile a ProgramSubmission whenever it is analyzed or created.
     */
    public function reconcileFromSubmission(ProgramSubmission $submission): ?DataProgram
    {
        $subDealerNorm = $this->normalizeDealerName($submission->dealer_name);
        $subProgNorm = $this->normalizeProgramName($submission->program_name);

        if (empty($subDealerNorm)) {
            return null;
        }

        $idReal = trim((string) ($submission->id_real ?? ''));

        // Find candidate DataProgram by Kode BT or Dealer Name
        $query = DataProgram::query();
        $tokens = array_filter(explode(' ', $subDealerNorm), fn ($t) => strlen($t) >= 3);

        $query->where(function ($q) use ($idReal, $submission, $tokens) {
            if (! empty($idReal) && $idReal !== '-') {
                $q->where('kode_bt', $idReal);
            }
            $q->orWhere('dealer_name', 'like', "%{$submission->dealer_name}%");
            foreach ($tokens as $token) {
                $q->orWhere('dealer_name', 'like', "%{$token}%");
            }
        });

        $candidates = $query->orderByDesc('id')->limit(50)->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $bestDp = null;
        $bestScore = 0;

        foreach ($candidates as $dp) {
            $score = $this->evaluateMatchScore($dp, $submission);
            if ($score !== null && $score > $bestScore) {
                $bestScore = $score;
                $bestDp = $dp;
            }
        }

        if ($bestDp) {
            $this->reconcileSingle($bestDp, $submission, false);

            // Push updated row directly to Google Spreadsheet master if configured
            try {
                $rowIndex = (int) str_replace('row_', '', (string) $bestDp->row_hash);
                app(DataProgramSyncService::class)->pushUpdatesToSpreadsheet([[
                    'row_index' => $rowIndex > 0 ? $rowIndex : null,
                    'kode_bt' => $bestDp->kode_bt,
                    'dealer_name' => $bestDp->dealer_name,
                    'program' => $bestDp->program,
                    'program_name' => $bestDp->program_name,
                    'periode' => $bestDp->periode,
                    'status_potong_purchase' => $bestDp->status_potong_purchase,
                    'status_potong_ar' => $bestDp->status_potong_ar,
                    'tgl_potong_tf' => $bestDp->tgl_potong_tf,
                    'cek_dokumen' => $bestDp->cek_dokumen,
                    'keterangan' => $bestDp->keterangan,
                    'cn' => $bestDp->cn,
                    'agrement' => $bestDp->agrement,
                    'cek_fp' => $bestDp->cek_fp,
                    'no_faktur_pajak' => $bestDp->no_faktur_pajak,
                    'ket_faktur_pajak' => $bestDp->ket_faktur_pajak,
                    'noted' => $bestDp->noted,
                ]]);
            } catch (\Throwable $e) {
                Log::warning('Push single reconciliation to spreadsheet failed: '.$e->getMessage());
            }

            return $bestDp;
        }

        return null;
    }
}

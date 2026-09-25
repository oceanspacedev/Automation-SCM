<?php

namespace App\Http\Controllers;

use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use App\Services\DataProgramSyncService;
use App\Services\ProgramReconciliationService;
use App\Services\WhatsAppService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataProgramController extends Controller
{
    public function __construct(
        protected DataProgramSyncService $syncService,
        protected ProgramReconciliationService $reconciliationService,
        protected WhatsAppService $waService
    ) {}

    /**
     * Get paginated list of Data Program records with stats and filter options.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DataProgram::query();

        // 1. Text Search
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('dealer_name', 'like', "%{$search}%")
                    ->orWhere('kode_bt', 'like', "%{$search}%")
                    ->orWhere('program_name', 'like', "%{$search}%")
                    ->orWhere('program', 'like', "%{$search}%")
                    ->orWhere('sales_person', 'like', "%{$search}%")
                    ->orWhere('id_gs', 'like', "%{$search}%")
                    ->orWhere('no_po', 'like', "%{$search}%")
                    ->orWhere('no_transaksi', 'like', "%{$search}%")
                    ->orWhere('no_faktur_pajak', 'like', "%{$search}%");
            });
        }

        // 2. Region / Big Region Filter
        if ($region = trim((string) $request->input('region'))) {
            $query->where(function ($q) use ($region) {
                $q->where('region', $region)
                    ->orWhere('big_region', $region);
            });
        }

        // 3. Program Filter
        if ($program = trim((string) $request->input('program'))) {
            $query->where(function ($q) use ($program) {
                $q->where('program', $program)
                    ->orWhere('program_name', $program);
            });
        }

        // 4. Status Potong Purchase Filter
        if ($statusPurchase = trim((string) $request->input('status_purchase'))) {
            $query->where('status_potong_purchase', $statusPurchase);
        }

        // 5. Status Potong AR Filter
        if ($statusAr = trim((string) $request->input('status_ar'))) {
            $query->where('status_potong_ar', $statusAr);
        }

        // 6. Cek Dokumen Filter
        if ($cekDokumen = trim((string) $request->input('cek_dokumen'))) {
            $query->where('cek_dokumen', $cekDokumen);
        }

        // 7. Keterangan Filter
        if ($keterangan = trim((string) $request->input('keterangan'))) {
            $query->where('keterangan', $keterangan);
        }

        // Calculate summary based on current filtered query
        $summaryQuery = clone $query;
        $totalNetPay = (float) $summaryQuery->sum('net_pay');
        $totalIncentive = (float) $summaryQuery->sum('incentive');
        $totalDpp = (float) $summaryQuery->sum('dpp');
        $totalPpn = (float) $summaryQuery->sum('ppn');
        $totalPph = (float) $summaryQuery->sum('nilai_pph');
        $countSudahPotong = (int) (clone $query)->where('status_potong_purchase', 'like', '%SUDAH%')->count();
        $countBisaPotong = (int) (clone $query)->where('status_potong_purchase', 'like', '%BISA%')->count();

        // Sort order
        $sortBy = (string) $request->input('sort_by', 'id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'id', 'dealer_name', 'kode_bt', 'program_name', 'region',
            'incentive', 'dpp', 'ppn', 'nilai_pph', 'net_pay',
            'status_potong_purchase', 'status_potong_ar', 'tgl_potong_tf',
        ];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = min(100, max(10, (int) $request->input('per_page', 50)));
        $items = $query->paginate($perPage);

        // Filter options for dropdowns (cached briefly or fetched distinctly)
        $regionOptions = DataProgram::whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        $programOptions = DataProgram::whereNotNull('program_name')
            ->where('program_name', '!=', '')
            ->distinct()
            ->orderBy('program_name')
            ->limit(50)
            ->pluck('program_name');

        $statusPurchaseOptions = DataProgram::whereNotNull('status_potong_purchase')
            ->where('status_potong_purchase', '!=', '')
            ->distinct()
            ->orderBy('status_potong_purchase')
            ->pluck('status_potong_purchase');

        $statusArOptions = DataProgram::whereNotNull('status_potong_ar')
            ->where('status_potong_ar', '!=', '')
            ->distinct()
            ->orderBy('status_potong_ar')
            ->pluck('status_potong_ar');

        $lastSyncedAt = DataProgram::latest('updated_at')->value('updated_at')?->toIso8601String();

        return response()->json([
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'summary' => [
                'total_rows' => $items->total(),
                'total_net_pay' => $totalNetPay,
                'total_incentive' => $totalIncentive,
                'total_dpp' => $totalDpp,
                'total_ppn' => $totalPpn,
                'total_pph' => $totalPph,
                'count_sudah_potong' => $countSudahPotong,
                'count_bisa_potong' => $countBisaPotong,
                'last_synced_at' => $lastSyncedAt,
            ],
            'filter_options' => [
                'regions' => $regionOptions,
                'programs' => $programOptions,
                'status_purchase' => $statusPurchaseOptions,
                'status_ar' => $statusArOptions,
            ],
        ]);
    }

    /**
     * Trigger synchronization from Google Spreadsheet.
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $customUrl = $request->input('url');
            $limit = max(0, (int) $request->input('limit', 0));

            if ($customUrl) {
                if (str_contains($customUrl, 'script.google.com/macros/s/')) {
                    $this->syncService->setWebAppUrl($customUrl);
                } else {
                    $this->syncService->setSpreadsheetUrl($customUrl);
                }
            }

            $result = $this->syncService->sync($customUrl, $limit);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan sinkronisasi: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get configured spreadsheet URL.
     */
    public function getUrl(): JsonResponse
    {
        return response()->json([
            'url' => $this->syncService->getSpreadsheetUrl(),
            'webapp_url' => $this->syncService->getWebAppUrl(),
        ]);
    }

    /**
     * Export filtered dataset to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DataProgram::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('dealer_name', 'like', "%{$search}%")
                    ->orWhere('kode_bt', 'like', "%{$search}%")
                    ->orWhere('program_name', 'like', "%{$search}%")
                    ->orWhere('program', 'like', "%{$search}%")
                    ->orWhere('sales_person', 'like', "%{$search}%")
                    ->orWhere('id_gs', 'like', "%{$search}%");
            });
        }

        if ($region = trim((string) $request->input('region'))) {
            $query->where(function ($q) use ($region) {
                $q->where('region', $region)->orWhere('big_region', $region);
            });
        }

        if ($statusPurchase = trim((string) $request->input('status_purchase'))) {
            $query->where('status_potong_purchase', $statusPurchase);
        }

        $headers = [
            'NAMA DEALER', 'PROGRAM', 'Kode BT', 'NAMA PROGRAM', 'PERIODE',
            'REGION', 'NO PO', 'ID GS', 'KODE SUPPLIER', 'Status DL',
            'SALES PERSON', 'TELEMARKETING', 'WAJIB PAJAK', 'TRF PPH',
            'INCENTIVE', 'DPP', 'DPP LAIN', 'PPN', 'NILAI PPH', 'NET PAY',
            'CEK PAJAK TARIF PPH', 'SELISIH', 'NOTE PPH', 'NO FAKTUR PAJAK',
            'KET FAKTUR PAJAK', 'NO PO/SJ', 'NO TRANSAKSI', 'Tgl Input',
            'Tgl Share CN', 'Lama Pending', 'Keterangan', 'CEK DOKUMEN',
            'STATUS POTONG BY PURCHASE', 'STATUS POTONG BY AR', 'TANGGAL POTONG/TF',
            'No. UID', 'NO. PEMBAYARAN', 'TGL INPUT BANK PPH', 'T/F',
            'TGL PROSES', 'TGL SJ', 'NO.SJ', 'INFO BANK', 'PENDING POTONGAN',
            'NPWP', 'NAMA NPWP', 'PROGRAM 2', 'CN', 'AGREMENT', 'CEK FP',
            'CEK EVIDANCE', 'Noted', 'Norek', 'Namrek', 'Bank', 'BIG REGION',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $query->chunk(1000, function ($rows) use ($output) {
                foreach ($rows as $r) {
                    fputcsv($output, [
                        $r->dealer_name, $r->program, $r->kode_bt, $r->program_name, $r->periode,
                        $r->region, $r->no_po, $r->id_gs, $r->kode_supplier, $r->status_dl,
                        $r->sales_person, $r->telemarketing, $r->wajib_pajak, $r->trf_pph,
                        $r->incentive, $r->dpp, $r->dpp_lain, $r->ppn, $r->nilai_pph, $r->net_pay,
                        $r->cek_pajak_tarif, $r->selisih, $r->note_pph, $r->no_faktur_pajak,
                        $r->ket_faktur_pajak, $r->no_po_sj, $r->no_transaksi, $r->tgl_input,
                        $r->tgl_share_cn, $r->lama_pending, $r->keterangan, $r->cek_dokumen,
                        $r->status_potong_purchase, $r->status_potong_ar, $r->tgl_potong_tf,
                        $r->no_uid, $r->no_pembayaran, $r->tgl_input_bank_pph, $r->tf_status,
                        $r->tgl_proses, $r->tgl_sj, $r->no_sj, $r->info_bank, $r->pending_potongan,
                        $r->npwp, $r->nama_npwp, $r->program_2, $r->cn, $r->agrement, $r->cek_fp,
                        $r->cek_evidance, $r->noted, $r->norek, $r->namrek, $r->bank, $r->big_region,
                    ]);
                }
            });

            fclose($output);
        }, 'data_program_'.date('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Receive incoming webhook payload from Google Apps Script (manual edit / batch).
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            if (empty($payload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payload webhook kosong.',
                ], 400);
            }

            $result = $this->syncService->saveWebhookPayload($payload);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'saved_count' => $result['saved_count'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses webhook Data Program: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reconcile Data Program records with Form Program submissions.
     * Matches dealer, program name, financial amounts, transfers Google Drive links, and populates status and keterangan.
     */
    public function reconcile(Request $request): JsonResponse
    {
        try {
            $options = [
                'limit' => max(0, (int) $request->input('limit', 100)),
                'year' => (string) $request->input('year', '2026'),
                'force' => (bool) $request->input('force', false),
                'only_unreconciled' => (bool) $request->input('only_unreconciled', true),
                'push_to_sheet' => (bool) $request->input('push_to_sheet', true),
                'search' => $request->input('search'),
                'program' => $request->input('program'),
            ];

            $result = $this->reconciliationService->reconcileBatch($options);

            $message = sprintf(
                'Rekonsiliasi selesai: %d dari %d data berhasil dicocokkan (%d BISA DI POTONG, %d BELUM BISA POTONG, %d link Drive dipindahkan).',
                $result['matched_count'],
                $result['total_evaluated'],
                $result['bisa_potong_count'],
                $result['belum_bisa_potong_count'],
                $result['drive_transferred_count']
            );

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan rekonsiliasi data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reconcile / test match a single DataProgram record with Form Program submissions.
     */
    public function reconcileRow(Request $request, int $id): JsonResponse
    {
        try {
            $dp = DataProgram::findOrFail($id);
            $force = $request->boolean('force', false);
            $pushToSheet = $request->boolean('push_to_sheet', true);

            $result = $this->reconciliationService->reconcileSingle($dp, null, $force);

            $matchedSubmission = null;
            if ($result['submission_id']) {
                $matchedSubmission = ProgramSubmission::find($result['submission_id']);
            }

            $sheetPushResult = null;
            if ($result['success'] && $pushToSheet) {
                try {
                    $rowIndex = (int) str_replace('row_', '', (string) $dp->row_hash);
                    $sheetPushResult = $this->syncService->pushUpdatesToSpreadsheet([[
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
                    ]]);
                } catch (\Throwable $e) {
                    Log::warning("Push single row reconcile to spreadsheet failed: {$e->getMessage()}");
                }
            }

            $isPkp = $this->reconciliationService->isPkp($dp, $matchedSubmission);

            $criteriaMatch = [
                'region' => $matchedSubmission ? $this->reconciliationService->isRegionMatch($matchedSubmission->region, $dp->region, $dp->big_region) : false,
                'kode_bt' => $matchedSubmission ? $this->reconciliationService->isBtMatch($matchedSubmission->id_real, $dp->kode_bt) : null,
                'dealer' => $matchedSubmission ? ($this->reconciliationService->normalizeDealerName($matchedSubmission->dealer_name) === $this->reconciliationService->normalizeDealerName($dp->dealer_name)) : false,
                'program' => $matchedSubmission ? ($this->reconciliationService->normalizeProgramName($matchedSubmission->program_name) === $this->reconciliationService->normalizeProgramName($dp->program_name)) : false,
                'financial' => $result['is_financial_match'],
            ];

            if ($result['success']) {
                $message = sprintf(
                    'Berhasil dicocokkan dengan Form Program #%d. Status: %s, Cek Dokumen: %s.',
                    $result['submission_id'],
                    $result['status'],
                    $result['cek_dokumen']
                );
            } else {
                $message = 'Belum ditemukan data Form Program yang cocok (Region, Kode BT, Nama Dealer, Program, atau Finansial tidak sesuai).';
            }

            return response()->json([
                'success' => true,
                'matched' => $result['success'],
                'message' => $message,
                'data' => $dp->fresh(),
                'details' => [
                    'submission_id' => $result['submission_id'],
                    'submission' => $matchedSubmission,
                    'status_potong_purchase' => $result['status'],
                    'cek_dokumen' => $result['cek_dokumen'],
                    'keterangan' => $result['keterangan'],
                    'is_pkp' => $isPkp,
                    'wajib_pajak' => $dp->wajib_pajak,
                    'drive_transferred' => $result['drive_transferred'],
                    'selisih' => $result['selisih'],
                    'is_financial_match' => $result['is_financial_match'],
                    'criteria_match' => $criteriaMatch,
                    'sheet_push' => $sheetPushResult,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'matched' => false,
                'message' => 'Gagal melakukan tes kecocokan data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics regarding reconciliation readiness.
     */
    public function reconcileStats(): JsonResponse
    {
        $totalDataProgram = DataProgram::count();
        $unreconciledDataProgram = DataProgram::where(function ($q) {
            $q->whereNull('status_potong_purchase')
                ->orWhere('status_potong_purchase', '')
                ->orWhere('status_potong_purchase', '-')
                ->orWhereNull('cn')
                ->orWhere('cn', '')
                ->orWhere('cn', '-');
        })->count();

        $totalSubmissions = ProgramSubmission::count();
        $analyzedSubmissions = ProgramSubmission::whereNotNull('dpp')->where('dpp', '>', 0)->count();

        return response()->json([
            'total_data_program' => $totalDataProgram,
            'unreconciled_data_program' => $unreconciledDataProgram,
            'reconciled_data_program' => max(0, $totalDataProgram - $unreconciledDataProgram),
            'total_submissions' => $totalSubmissions,
            'analyzed_submissions' => $analyzedSubmissions,
        ]);
    }

    /**
     * Update a DataProgram record and optionally push updates to Google Spreadsheet.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $dp = DataProgram::findOrFail($id);

        $validated = $request->validate([
            'status_potong_purchase' => 'nullable|string|max:100',
            'cek_dokumen' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
            'noted' => 'nullable|string',
            'cn' => 'nullable|string',
            'agrement' => 'nullable|string',
            'cek_fp' => 'nullable|string',
            'status_potong_ar' => 'nullable|string|max:100',
            'tgl_potong_tf' => 'nullable|string|max:50',
            'push_to_sheet' => 'nullable|boolean',
        ]);

        $pushToSheet = $validated['push_to_sheet'] ?? true;
        unset($validated['push_to_sheet']);

        $dp->fill($validated);
        $dp->save();

        $sheetPushResult = null;
        if ($pushToSheet) {
            try {
                $rowIndex = (int) str_replace('row_', '', (string) $dp->row_hash);
                $sheetPushResult = $this->syncService->pushUpdatesToSpreadsheet([[
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
                ]]);
            } catch (\Throwable $e) {
                Log::warning("Push single row update to spreadsheet failed: {$e->getMessage()}");
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data Program berhasil diperbarui.'.($sheetPushResult && ($sheetPushResult['success'] ?? false) ? ' Perubahan juga telah dikirim ke Google Spreadsheet.' : ''),
            'data' => $dp,
            'sheet_push' => $sheetPushResult,
        ]);
    }

    /**
     * Send WhatsApp notification to Telemarketing for DataProgram claim offer.
     */
    public function sendWaToTelemarketing(Request $request, int $id): JsonResponse
    {
        $dp = DataProgram::findOrFail($id);
        $phone = $request->input('phone');

        $result = $this->waService->sendDataProgramClaimNotificationToTelemarketing($dp, $phone);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'provider_id' => $result['provider_id'] ?? null,
        ]);
    }

    /**
     * Send WhatsApp notification to AR for DataProgram claim.
     */
    public function sendWaToAr(Request $request, int $id): JsonResponse
    {
        $dp = DataProgram::findOrFail($id);
        $phone = $request->input('phone');

        $result = $this->waService->sendDataProgramClaimNotificationToAr($dp, $phone);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'provider_id' => $result['provider_id'] ?? null,
        ]);
    }
}

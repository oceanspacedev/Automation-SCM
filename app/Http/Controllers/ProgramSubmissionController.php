<?php

namespace App\Http\Controllers;

use App\Console\Commands\AutoAnalyzeProgramAiCommand;
use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use App\Services\DataProgramSyncService;
use App\Services\DocumentAnalysisService;
use App\Services\ProgramSubmissionService;
use App\Services\WhatsAppService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramSubmissionController extends Controller
{
    public function __construct(
        protected ProgramSubmissionService $service,
        protected DocumentAnalysisService $aiService,
        protected WhatsAppService $waService,
    ) {}

    /**
     * List program submissions with filtering & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProgramSubmission::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('dealer_name', 'like', "%{$search}%")
                    ->orWhere('id_real', 'like', "%{$search}%")
                    ->orWhere('sales_name', 'like', "%{$search}%")
                    ->orWhere('program_name', 'like', "%{$search}%")
                    ->orWhere('no_po_sj', 'like', "%{$search}%")
                    ->orWhere('no_transaksi', 'like', "%{$search}%");
            });
        }

        if ($region = trim((string) $request->input('region'))) {
            $query->where('region', $region);
        }

        if ($program = trim((string) $request->input('program'))) {
            $query->where('program_name', $program);
        }

        if ($statusPurchase = trim((string) $request->input('status_purchase'))) {
            $query->where('status_potong_purchase', $statusPurchase);
        }

        if ($keterangan = trim((string) $request->input('keterangan'))) {
            $query->where('keterangan', $keterangan);
        }

        if ($source = trim((string) $request->input('source'))) {
            if ($source === 'web_form') {
                $query->where('raw_data->source', 'web_form');
            } elseif ($source === 'spreadsheet') {
                $query->where(function ($q) {
                    $q->whereNull('raw_data->source')
                        ->orWhere('raw_data->source', '!=', 'web_form');
                });
            }
        }

        $cols = [
            'id', 'submission_timestamp', 'region', 'id_real', 'dealer_name',
            'program_name', 'sales_name', 'whatsapp', 'credit_note_url', 'agreement_url',
            'tax_invoice_url', 'incentive', 'dpp', 'dpp_lain', 'ppn', 'nilai_pph',
            'net_pay', 'cek_pajak_tarif_pph', 'selisih', 'note_pph', 'no_faktur',
            'tgl_faktur', 'no_po_sj', 'no_transaksi', 'tgl_input',
            'tgl_share_cn', 'lama_pending', 'keterangan', 'cek_dokumen',
            'status_potong_purchase', 'status_potong_ar', 'tgl_potong_tf', 'doc_validation', 'is_manual_edit', 'raw_data', 'created_at', 'updated_at',
        ];

        $perPage = min((int) $request->input('per_page', 15), 100);
        $submissions = $query->select($cols)->orderByDesc('id')->paginate($perPage);

        // Cache regions dropdown as plain array
        $regions = Cache::remember('program_submissions_regions_list_v2', 300, function () {
            return ProgramSubmission::whereNotNull('region')
                ->where('region', '!=', '')
                ->distinct()
                ->orderBy('region')
                ->pluck('region')
                ->filter(function ($r) {
                    $trimmed = trim((string) $r);

                    return $trimmed !== '' && $trimmed !== '\\' && $trimmed !== '-';
                })
                ->values()
                ->all();
        });

        // Cache programs dropdown as plain array
        $programs = Cache::remember('program_submissions_programs_list_v2', 300, function () {
            return ProgramSubmission::whereNotNull('program_name')
                ->where('program_name', '!=', '')
                ->distinct()
                ->orderBy('program_name')
                ->pluck('program_name')
                ->filter(function ($p) {
                    $trimmed = trim((string) $p);

                    return $trimmed !== '' && $trimmed !== '.' && $trimmed !== '-';
                })
                ->values()
                ->all();
        });

        if ($regions instanceof Collection) {
            $regions = $regions->values()->all();
        } elseif (! is_array($regions)) {
            $regions = [];
        }

        if ($programs instanceof Collection) {
            $programs = $programs->values()->all();
        } elseif (! is_array($programs)) {
            $programs = [];
        }

        // Fast lookup for latest sync timestamp using indexed ID
        $lastSyncedAt = Cache::remember('program_submissions_latest_time', 30, function () {
            return ProgramSubmission::orderByDesc('id')->value('updated_at')?->toIso8601String();
        });

        $totalSubmissions = Cache::remember('program_submissions_total_count', 30, function () {
            return ProgramSubmission::count();
        });

        $webFormTotal = ProgramSubmission::where('raw_data->source', 'web_form')->count();

        return response()->json([
            'submissions' => $submissions,
            'regions' => $regions,
            'programs' => $programs,
            'status_purchase_options' => ProgramSubmission::STATUS_PURCHASE_OPTIONS,
            'keterangan_options' => ProgramSubmission::KETERANGAN_OPTIONS,
            'total_submissions' => $totalSubmissions,
            'web_form_total' => $webFormTotal,
            'configured_webapp_url' => $this->service->getWebAppUrl(),
            'last_synced_at' => $lastSyncedAt,
        ]);
    }

    /**
     * Export program submissions to an Excel (.xlsx) file.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = ProgramSubmission::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('dealer_name', 'like', "%{$search}%")
                    ->orWhere('id_real', 'like', "%{$search}%")
                    ->orWhere('sales_name', 'like', "%{$search}%")
                    ->orWhere('program_name', 'like', "%{$search}%")
                    ->orWhere('no_po_sj', 'like', "%{$search}%")
                    ->orWhere('no_transaksi', 'like', "%{$search}%");
            });
        }

        if ($region = trim((string) $request->input('region'))) {
            $query->where('region', $region);
        }

        if ($program = trim((string) $request->input('program'))) {
            $query->where('program_name', $program);
        }

        if ($statusPurchase = trim((string) $request->input('status_purchase'))) {
            $query->where('status_potong_purchase', $statusPurchase);
        }

        if ($keterangan = trim((string) $request->input('keterangan'))) {
            $query->where('keterangan', $keterangan);
        }

        $cols = [
            'id', 'submission_timestamp', 'region', 'id_real', 'dealer_name',
            'program_name', 'sales_name', 'whatsapp', 'credit_note_url', 'agreement_url',
            'tax_invoice_url', 'incentive', 'dpp', 'dpp_lain', 'ppn', 'nilai_pph',
            'net_pay', 'cek_pajak_tarif_pph', 'selisih', 'note_pph', 'no_faktur',
            'tgl_faktur', 'no_po_sj', 'no_transaksi', 'tgl_input',
            'tgl_share_cn', 'lama_pending', 'keterangan', 'cek_dokumen',
            'status_potong_purchase', 'status_potong_ar', 'tgl_potong_tf',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Form Program');

        $headers = [
            'No',
            'Waktu Submission',
            'Region',
            'ID Real',
            'Nama Dealer',
            'Nama Program',
            'Nama Sales',
            'No. WhatsApp',
            'Link CN',
            'Link Agreement',
            'Link Faktur Pajak',
            'Incentive',
            'DPP',
            'DPP Lain',
            'PPN',
            'Nilai PPh',
            'Net Pay',
            'Cek Pajak Tarif PPh',
            'Selisih',
            'Note PPh',
            'No Faktur',
            'Tgl Faktur',
            'No PO / SJ',
            'No Transaksi',
            'Tgl Input',
            'Tgl Share CN',
            'Lama Pending',
            'Keterangan',
            'Cek Dokumen',
            'Status Potong Purchase',
            'Status Potong AR',
            'Tgl Potong / TF',
        ];

        $sheet->fromArray([$headers], null, 'A1');

        $highestCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$highestCol}1")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A1:{$highestCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF3F4F6');
        $sheet->getStyle("A1:{$highestCol}1")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $dataRows = [];
        $no = 1;
        $query->select($cols)->orderByDesc('id')->chunk(1000, function ($items) use (&$dataRows, &$no) {
            foreach ($items as $item) {
                $dataRows[] = [
                    $no++,
                    $item->submission_timestamp ?? '',
                    $item->region ?? '',
                    $item->id_real ?? '',
                    $item->dealer_name ?? '',
                    $item->program_name ?? '',
                    $item->sales_name ?? '',
                    $item->whatsapp ?? ($item->effective_whatsapp ?? ''),
                    $item->credit_note_url ?? '',
                    $item->agreement_url ?? '',
                    $item->tax_invoice_url ?? '',
                    $item->incentive ?? '',
                    $item->dpp ?? '',
                    $item->dpp_lain ?? '',
                    $item->ppn ?? '',
                    $item->nilai_pph ?? '',
                    $item->net_pay ?? '',
                    $item->cek_pajak_tarif_pph ?? '',
                    $item->selisih ?? '',
                    $item->note_pph ?? '',
                    $item->no_faktur ?? '',
                    $item->tgl_faktur ?? '',
                    $item->no_po_sj ?? '',
                    $item->no_transaksi ?? '',
                    $item->tgl_input ?? '',
                    $item->tgl_share_cn ?? '',
                    $item->lama_pending ?? '',
                    $item->keterangan ?? '',
                    $item->cek_dokumen ?? '',
                    $item->status_potong_purchase ?? '',
                    $item->status_potong_ar ?? '',
                    $item->tgl_potong_tf ?? '',
                ];
            }
        });

        if (! empty($dataRows)) {
            $sheet->fromArray($dataRows, null, 'A2');
        }

        $highestColumnIndex = count($headers);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        $filename = 'form_program_'.date('Ymd_His').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    /**
     * Update manual tracking & status potong for a program submission.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $submission = ProgramSubmission::findOrFail($id);

        $validated = $request->validate([
            'dealer_name' => 'nullable|string|max:255',
            'id_real' => 'nullable|string|max:255',
            'program_name' => 'nullable|string|max:255',
            'sales_name' => 'nullable|string|max:255',
            'credit_note_url' => 'nullable|string',
            'agreement_url' => 'nullable|string',
            'tax_invoice_url' => 'nullable|string',
            'no_po_sj' => 'nullable|string|max:255',
            'no_transaksi' => 'nullable|string|max:255',
            'tgl_input' => 'nullable|string|max:255',
            'tgl_share_cn' => 'nullable|string|max:255',
            'lama_pending' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
            'cek_dokumen' => 'nullable|string',
            'status_potong_purchase' => 'nullable|string|max:255',
            'status_potong_ar' => 'nullable|string|max:255',
            'tgl_potong_tf' => 'nullable|string|max:255',
            'incentive' => 'nullable|numeric',
            'dpp' => 'nullable|numeric',
            'dpp_lain' => 'nullable|numeric',
            'ppn' => 'nullable|numeric',
            'nilai_pph' => 'nullable|numeric',
            'net_pay' => 'nullable|numeric',
            'cek_pajak_tarif_pph' => 'nullable|numeric',
            'selisih' => 'nullable|numeric',
            'note_pph' => 'nullable|string|max:100',
            'no_faktur' => 'nullable|string|max:100',
            'tgl_faktur' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'doc_validation' => 'nullable|array',
            'is_manual_edit' => 'nullable|boolean',
        ]);

        $manualFields = ['dealer_name', 'id_real', 'program_name', 'sales_name', 'whatsapp', 'credit_note_url', 'agreement_url', 'tax_invoice_url'];
        foreach ($manualFields as $field) {
            if ($request->has($field)) {
                $validated['is_manual_edit'] = true;
                break;
            }
        }

        if (isset($validated['nilai_pph']) && isset($validated['cek_pajak_tarif_pph']) && ! array_key_exists('selisih', $validated)) {
            $validated['selisih'] = round((float) $validated['nilai_pph'] - (float) $validated['cek_pajak_tarif_pph'], 2);
        }

        $submission->update($validated);

        if (! empty($submission->id_real)) {
            $matchingDp = DataProgram::where('kode_bt', $submission->id_real)->first();
            if ($matchingDp) {
                $dpUpdates = [];
                if (isset($validated['status_potong_purchase'])) {
                    $dpUpdates['status_potong_purchase'] = $validated['status_potong_purchase'];
                }
                if (isset($validated['status_potong_ar'])) {
                    $dpUpdates['status_potong_ar'] = $validated['status_potong_ar'];
                    if ($validated['status_potong_ar'] === 'DONE' && empty($dpUpdates['status_potong_purchase'])) {
                        $dpUpdates['status_potong_purchase'] = 'SUDAH POTONG';
                    }
                }
                if (isset($validated['tgl_potong_tf'])) {
                    $dpUpdates['tgl_potong_tf'] = $validated['tgl_potong_tf'];
                }
                if (isset($validated['cek_dokumen'])) {
                    $dpUpdates['cek_dokumen'] = $validated['cek_dokumen'];
                }
                if (! empty($dpUpdates)) {
                    $matchingDp->update($dpUpdates);
                    try {
                        $rowIndex = (int) str_replace('row_', '', (string) $matchingDp->row_hash);
                        app(DataProgramSyncService::class)->pushUpdatesToSpreadsheet([[
                            'row_index' => $rowIndex > 0 ? $rowIndex : null,
                            'kode_bt' => $matchingDp->kode_bt,
                            'dealer_name' => $matchingDp->dealer_name,
                            'program' => $matchingDp->program,
                            'program_name' => $matchingDp->program_name,
                            'periode' => $matchingDp->periode,
                            'status_potong_purchase' => $matchingDp->status_potong_purchase,
                            'status_potong_ar' => $matchingDp->status_potong_ar,
                            'tgl_potong_tf' => $matchingDp->tgl_potong_tf,
                            'cek_dokumen' => $matchingDp->cek_dokumen,
                            'keterangan' => $matchingDp->keterangan,
                            'cn' => $matchingDp->cn,
                            'agrement' => $matchingDp->agrement,
                            'cek_fp' => $matchingDp->cek_fp,
                            'no_faktur_pajak' => $matchingDp->no_faktur_pajak,
                            'ket_faktur_pajak' => $matchingDp->ket_faktur_pajak,
                            'noted' => $matchingDp->noted,
                        ]]);
                    } catch (\Throwable $e) {
                        Log::warning("Gagal push matching DP saat update submission: {$e->getMessage()}");
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data pengajuan program berhasil diperbarui.',
            'submission' => $submission,
        ]);
    }

    /**
     * Swap swapped documents (e.g. Agr <-> Faktur).
     */
    public function swapDocs(Request $request, int|string $id): JsonResponse
    {
        $submission = ProgramSubmission::findOrFail($id);
        $type = $request->input('type', 'agr_faktur');

        $docValidation = $submission->doc_validation ?? [];

        if ($type === 'agr_faktur') {
            $temp = $submission->agreement_url;
            $submission->agreement_url = $submission->tax_invoice_url;
            $submission->tax_invoice_url = $temp;

            if (isset($docValidation['agr'])) {
                $docValidation['agr']['status'] = 'valid';
                $docValidation['agr']['message'] = 'Posisi file telah ditukar menjadi Agreement';
            }
            if (isset($docValidation['faktur'])) {
                $docValidation['faktur']['status'] = 'valid';
                $docValidation['faktur']['message'] = 'Posisi file telah ditukar menjadi Faktur Pajak';
            }
        } elseif ($type === 'cn_agr') {
            $temp = $submission->credit_note_url;
            $submission->credit_note_url = $submission->agreement_url;
            $submission->agreement_url = $temp;

            if (isset($docValidation['cn'])) {
                $docValidation['cn']['status'] = 'valid';
                $docValidation['cn']['message'] = 'Posisi file telah ditukar menjadi Credit Note';
            }
            if (isset($docValidation['agr'])) {
                $docValidation['agr']['status'] = 'valid';
                $docValidation['agr']['message'] = 'Posisi file telah ditukar menjadi Agreement';
            }
        } elseif ($type === 'cn_faktur') {
            $temp = $submission->credit_note_url;
            $submission->credit_note_url = $submission->tax_invoice_url;
            $submission->tax_invoice_url = $temp;

            if (isset($docValidation['cn'])) {
                $docValidation['cn']['status'] = 'valid';
                $docValidation['cn']['message'] = 'Posisi file telah ditukar menjadi Credit Note';
            }
            if (isset($docValidation['faktur'])) {
                $docValidation['faktur']['status'] = 'valid';
                $docValidation['faktur']['message'] = 'Posisi file telah ditukar menjadi Faktur Pajak';
            }
        }

        $submission->doc_validation = $docValidation;
        $submission->is_manual_edit = true;

        if (! empty($submission->credit_note_url) && ! empty($submission->agreement_url) && ! empty($submission->tax_invoice_url)) {
            $allValid = true;
            foreach (['cn', 'agr', 'faktur'] as $docKey) {
                if (isset($docValidation[$docKey]) && in_array($docValidation[$docKey]['status'] ?? '', ['swapped', 'invalid'])) {
                    $allValid = false;
                }
            }
            if ($allValid) {
                $submission->cek_dokumen = 'LENGKAP';
                if ($submission->status_potong_purchase === 'BELUM BISA POTONG' || empty($submission->status_potong_purchase)) {
                    $submission->status_potong_purchase = 'BISA DI POTONG';
                }
            }
        }

        $submission->save();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil ditukar posisinya.',
            'submission' => $submission->fresh(),
        ]);
    }

    /**
     * Delete a program submission.
     */
    public function destroy(int|string $id): JsonResponse
    {
        $submission = ProgramSubmission::findOrFail($id);
        $dealerName = $submission->dealer_name ?: $submission->id_real ?: 'Pengajuan';
        $submission->delete();

        return response()->json([
            'success' => true,
            'message' => "Pengajuan program '{$dealerName}' berhasil dihapus.",
        ]);
    }

    /**
     * Trigger synchronization from Google Apps Script Web App.
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $customUrl = $request->input('url');
            $limit = max(0, (int) $request->input('limit', 0));
            if ($customUrl) {
                $this->service->setWebAppUrl($customUrl);
            }

            $result = $this->service->syncFromWebAppUrl($customUrl, $limit);

            // If new records were imported, automatically dispatch background AI analysis (hanya di non-local)
            if (! app()->environment('local') && ($result['new_count'] ?? 0) > 0) {
                $this->dispatchBackgroundAi(min(100, max(5, (int) $result['new_count'])));
            }

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
     * Get Google Spreadsheet and Apps Script Web App config.
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'webapp_url' => $this->service->getWebAppUrl(),
            'spreadsheet_url' => $this->service->getSpreadsheetUrl(),
        ]);
    }

    /**
     * Save Google Apps Script Web App URL and Spreadsheet URL config.
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'nullable|url',
            'spreadsheet_url' => 'nullable|url',
        ]);

        if ($request->filled('url')) {
            $this->service->setWebAppUrl($request->input('url'));
        }

        if ($request->filled('spreadsheet_url')) {
            $this->service->setSpreadsheetUrl($request->input('spreadsheet_url'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi Google Spreadsheet berhasil disimpan.',
            'configured_webapp_url' => $this->service->getWebAppUrl(),
            'configured_spreadsheet_url' => $this->service->getSpreadsheetUrl(),
        ]);
    }

    /**
     * Manually push a single submission to Google Spreadsheet.
     */
    public function pushToSpreadsheet(int|string $id): JsonResponse
    {
        $submission = ProgramSubmission::findOrFail($id);
        $result = $this->service->appendSubmissionToSpreadsheet($submission);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Analyze a single submission with AI Router.
     */
    public function analyzeAi(int|string $id): JsonResponse
    {
        $submission = ProgramSubmission::findOrFail($id);

        try {
            $result = $this->aiService->analyzeSubmission($submission);

            return response()->json([
                'success' => true,
                'message' => 'Analisis AI berhasil diproses.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menganalisis dokumen dengan AI: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Analyze multiple submissions in batch with AI Router.
     */
    public function analyzeAiBatch(Request $request): JsonResponse
    {
        $ids = $request->input('ids');
        $year = $request->input('year', '2026');
        $batchSize = min(30, max(5, (int) $request->input('batch_size', $request->input('limit', 20))));

        $query = ProgramSubmission::query();
        if ($year) {
            $query->where('submission_timestamp', 'like', "{$year}%");
        }

        $query->where(function ($q) {
            $q->whereNull('cek_dokumen')
                ->orWhere('cek_dokumen', '')
                ->orWhereNull('status_potong_purchase')
                ->orWhere('status_potong_purchase', '');
        })->orderByDesc('id');

        $totalRemaining = (clone $query)->count();

        if (! is_array($ids) || empty($ids)) {
            if ($totalRemaining === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Semua data program tahun '.$year.' sudah selesai dianalisis.',
                    'remaining' => 0,
                    'data' => [
                        'total' => 0,
                        'success_count' => 0,
                        'error_count' => 0,
                        'results' => [],
                    ],
                ]);
            }

            // Always take safe batch size (max 30) per request so Nginx NEVER hits 504 Gateway Timeout
            $ids = $query->limit($batchSize)->pluck('id')->all();
        }

        try {
            $result = $this->aiService->analyzeBatch($ids);
            $remaining = max(0, $totalRemaining - count($ids));

            return response()->json([
                'success' => true,
                'message' => "Batch berhasil dianalisis ({$result['success_count']} data). Sisa: {$remaining} data.",
                'remaining' => $remaining,
                'total_remaining_before' => $totalRemaining,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses analisis AI batch: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get current AI Router config and available models.
     */
    public function getAiConfig(): JsonResponse
    {
        $config = $this->aiService->getConfig();
        $models = $this->aiService->getAvailableModels();

        return response()->json([
            'config' => [
                'base_url' => $config['base_url'],
                'api_key_masked' => ! empty($config['api_key'])
                    ? substr($config['api_key'], 0, 8).'••••••••'.substr($config['api_key'], -4)
                    : '',
                'model' => $config['model'],
                'has_key' => ! empty($config['api_key']),
            ],
            'models' => $models,
        ]);
    }

    /**
     * Save AI Router configuration / selected model.
     */
    public function saveAiConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'model' => 'required|string',
        ]);

        $current = $this->aiService->getConfig();
        $baseUrl = ! empty($validated['base_url']) ? $validated['base_url'] : $current['base_url'];
        $apiKey = ! empty($validated['api_key']) ? $validated['api_key'] : $current['api_key'];

        $this->aiService->saveConfig($baseUrl, $apiKey, $validated['model']);

        return response()->json([
            'success' => true,
            'message' => "Model AI berhasil diubah ke: {$validated['model']}",
            'config' => [
                'base_url' => $baseUrl,
                'model' => $validated['model'],
                'has_key' => ! empty($apiKey),
            ],
        ]);
    }

    /**
     * Get real-time AI status statistics for 2026 submissions.
     */
    public function getAiStatus(): JsonResponse
    {
        $config = $this->aiService->getConfig();

        $total2026 = ProgramSubmission::where('submission_timestamp', 'like', '2026%')->count();
        $analyzed2026 = ProgramSubmission::where('submission_timestamp', 'like', '2026%')
            ->whereNotNull('status_potong_purchase')
            ->where('status_potong_purchase', '!=', '')
            ->count();
        $unanalyzed2026 = max(0, $total2026 - $analyzed2026);

        $bisaPotong = ProgramSubmission::where('submission_timestamp', 'like', '2026%')
            ->where('status_potong_purchase', 'BISA DI POTONG')
            ->count();
        $belumBisaPotong = ProgramSubmission::where('submission_timestamp', 'like', '2026%')
            ->where('status_potong_purchase', 'BELUM BISA POTONG')
            ->count();

        $runningInfo = Cache::get(AutoAnalyzeProgramAiCommand::CACHE_KEY_STATUS);
        $isRunning = false;

        if (is_array($runningInfo) && ! empty($runningInfo['is_running'])) {
            $heartbeat = (int) ($runningInfo['last_heartbeat'] ?? 0);
            if (time() - $heartbeat < 60) {
                $isRunning = true;
            } else {
                $runningInfo['is_running'] = false;
            }
        }

        return response()->json([
            'current_model' => $config['model'],
            'total_2026' => $total2026,
            'analyzed_2026' => $analyzed2026,
            'unanalyzed_2026' => $unanalyzed2026,
            'bisa_potong_count' => $bisaPotong,
            'belum_bisa_potong_count' => $belumBisaPotong,
            'is_running' => $isRunning,
            'running_info' => $runningInfo,
        ]);
    }

    /**
     * Run AI analysis in the background without blocking the web server.
     */
    public function runAiInBackground(Request $request): JsonResponse
    {
        $year = (string) $request->input('year', '2026');
        $all = $request->boolean('all', true);
        $limit = $all ? 0 : min(500, max(5, (int) $request->input('limit', 50)));

        $this->dispatchBackgroundAi($limit, $year, $all);

        $msg = $all
            ? "AI di latar belakang berhasil dijalankan untuk SELURUH data tahun {$year}."
            : "AI di latar belakang berhasil dijalankan untuk {$limit} data tahun {$year}.";

        return response()->json([
            'success' => true,
            'message' => $msg,
        ]);
    }

    /**
     * Dispatch artisan auto-analyze command in background process.
     */
    protected function dispatchBackgroundAi(int $limit = 25, string $year = '2026', bool $all = false): void
    {
        try {
            $phpBinary = $this->getPhpCliBinary();
            $artisan = escapeshellarg(base_path('artisan'));
            $yearArg = escapeshellarg("--year={$year}");
            $limitArg = ($all || $limit <= 0) ? '--all' : '--limit='.(int) $limit;
            $sleepArg = '--sleep=0.05';
            $forceArg = '--force';

            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = "start /B {$phpBinary} {$artisan} program:auto-analyze-ai {$yearArg} {$limitArg} {$sleepArg} {$forceArg}";
                pclose(popen($cmd, 'r'));
            } else {
                $cmd = "nohup {$phpBinary} {$artisan} program:auto-analyze-ai {$yearArg} {$limitArg} {$sleepArg} {$forceArg} > /dev/null 2>&1 &";
                exec($cmd);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal memulai background AI process: '.$e->getMessage());
        }
    }

    /**
     * Find the appropriate PHP CLI executable binary.
     */
    protected function getPhpCliBinary(): string
    {
        $binary = PHP_BINARY;

        if (str_contains($binary, 'fpm') || str_contains($binary, 'cgi')) {
            $possiblePaths = [
                PHP_BINDIR.'/php',
                '/usr/bin/php',
                '/usr/local/bin/php',
                'php',
            ];
            foreach ($possiblePaths as $path) {
                if (@is_executable($path)) {
                    return escapeshellarg($path);
                }
            }

            return 'php';
        }

        return escapeshellarg($binary);
    }

    /**
     * Test connection to AI Router.
     */
    public function testAiConnection(Request $request): JsonResponse
    {
        try {
            $res = $this->aiService->testConnection(
                $request->input('base_url'),
                $request->input('api_key'),
                $request->input('model'),
            );

            return response()->json($res);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Receive incoming webhook from Google Apps Script (e.g. onFormSubmit).
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            if (empty($payload)) {
                return response()->json(['success' => false, 'message' => 'Payload kosong.'], 400);
            }

            $submission = $this->service->saveWebhookPayload($payload);

            // Automatically analyze newly submitted document with AI
            $aiAnalyzed = false;
            try {
                $this->aiService->analyzeSubmission($submission);
                $aiAnalyzed = true;
            } catch (\Throwable $e) {
                Log::warning("Auto-analysis on webhook failed for ID {$submission->id}: ".$e->getMessage());
            }

            $fresh = $submission->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Data respon berhasil disimpan'.($aiAnalyzed ? ' dan dianalisis AI.' : '.'),
                'id' => $submission->id,
                'ai_analyzed' => $aiAnalyzed,
                'status_potong_purchase' => $fresh->status_potong_purchase,
                'cek_dokumen' => $fresh->cek_dokumen,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses webhook: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send WhatsApp notification to AR for program claim potong confirmation.
     */
    public function sendWaToAr(Request $request, int|string $id): JsonResponse
    {
        $submission = ProgramSubmission::findOrFail($id);
        $phone = $request->input('phone');

        $result = $this->waService->sendProgramClaimNotificationToAr($submission, $phone);

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
     * Send WhatsApp notification to Telemarketing for program claim offer to dealer.
     */
    public function sendWaToTelemarketing(Request $request, int|string $id): JsonResponse
    {
        $submission = ProgramSubmission::findOrFail($id);
        $phone = $request->input('phone');

        $result = $this->waService->sendProgramClaimNotificationToTelemarketing($submission, $phone);

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
     * Send WhatsApp notification to sales for incorrect/invalid documents.
     */
    public function sendWaDocError(Request $request, int|string $id): JsonResponse
    {
        $submission = ProgramSubmission::findOrFail($id);
        $phone = $request->input('phone');

        $result = $this->waService->sendProgramDocumentErrorNotification($submission, $phone);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'wa_url' => $result['wa_url'] ?? null,
                'recipient_phone' => $result['recipient_phone'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'provider_id' => $result['provider_id'] ?? null,
            'wa_url' => $result['wa_url'] ?? null,
            'recipient_phone' => $result['recipient_phone'] ?? null,
        ]);
    }

    /**
     * Get options for the public submission form (Programs, Regions, Sales names).
     */
    public function formOptions(): JsonResponse
    {
        $defaultPrograms = [
            'PROGRAM DSA FEBRUARI 2026',
            'PROGRAM DSA MARET 2026',
            'PROGRAM SO R14T MARET 2026',
            'PROGRAM DSA MEI 2026',
            'PROGRAM FS PO C100 & C100X MEI 2026',
            'PROGRAM ST C100 & C100X MEI 2026',
            'PROGRAM DSA JUNI 2026',
            'PROGRAM PROMOTION NOTE 80 4+128 JUNI 2026',
            'PROGRAM PROMOTION C100X SERIES JUNI 2026',
            'PROGRAM DSA JULI 2026',
            'PROGRAM DSA AGUSTUS 2026',
        ];

        $regions = [
            'BIG BANDUNG',
            'BIG KARAWANG',
            'BIG TASIK',
            'BIG CIREBON',
        ];

        $sales = Cache::remember('program_submissions_sales_list_v2', 300, function () {
            return ProgramSubmission::whereNotNull('sales_name')
                ->where('sales_name', '!=', '')
                ->distinct()
                ->orderBy('sales_name')
                ->pluck('sales_name')
                ->filter(fn ($s) => trim((string) $s) !== '' && trim((string) $s) !== '-')
                ->values()
                ->all();
        });

        $dbPrograms = ProgramSubmission::whereNotNull('program_name')
            ->where('program_name', 'like', '%2026%')
            ->distinct()
            ->orderBy('program_name')
            ->pluck('program_name')
            ->filter(fn ($p) => trim((string) $p) !== '' && trim((string) $p) !== '-')
            ->values()
            ->all();

        $allPrograms = array_values(array_unique(array_merge($defaultPrograms, $dbPrograms)));

        return response()->json([
            'programs' => $allPrograms,
            'regions' => $regions,
            'sales' => $sales,
        ]);
    }

    /**
     * Pre-validate uploaded files with AI before final form submission.
     */
    public function preValidateForm(Request $request): JsonResponse
    {
        $formData = [
            'program_name' => (string) $request->input('program_name', ''),
            'region' => (string) $request->input('region', ''),
            'id_real' => (string) $request->input('id_real', ''),
            'dealer_name' => (string) $request->input('dealer_name', ''),
            'sales_name' => (string) $request->input('sales_name', ''),
            'whatsapp' => (string) $request->input('whatsapp', ''),
            'is_pkp' => $request->boolean('is_pkp', false),
        ];

        $files = [
            'cn' => $request->file('credit_note_file') ?: $request->input('credit_note_url'),
            'agr' => $request->file('agreement_file') ?: $request->input('agreement_url'),
            'faktur' => $request->file('tax_invoice_file') ?: $request->input('tax_invoice_url'),
        ];

        try {
            $inspection = $this->aiService->inspectDocumentFiles($formData, $files);

            return response()->json([
                'success' => true,
                'data' => $inspection,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa dokumen: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Handle public form submission from Sales / Customer.
     */
    public function submitForm(Request $request): JsonResponse
    {
        $request->validate([
            'program_name' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'id_real' => [
                'required',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    $val = trim((string) $value);
                    if ($val === '' || trim($val, "- \t\n\r\0\x0B") === '') {
                        $fail('ID REALME wajib diisi dan tidak boleh hanya berisi tanda hubung (-).');
                    }
                },
            ],
            'dealer_name' => ['required', 'string', 'max:255'],
            'sales_name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'credit_note_file' => ['required_without:credit_note_url', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'agreement_file' => ['required_without:agreement_url', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'tax_invoice_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
        ], [
            'program_name.required' => 'Nama Program wajib dipilih atau diisi.',
            'region.required' => 'Region wajib dipilih.',
            'id_real.required' => 'ID REALME wajib diisi.',
            'dealer_name.required' => 'Nama Dealer wajib diisi.',
            'sales_name.required' => 'Nama Sales (DM) wajib dipilih atau diisi.',
            'credit_note_file.required_without' => 'Dokumen Credit Note/Invoice wajib diunggah.',
            'agreement_file.required_without' => 'Dokumen Agreement wajib diunggah.',
        ]);

        $formData = [
            'program_name' => trim((string) $request->input('program_name')),
            'region' => trim((string) $request->input('region')),
            'id_real' => trim((string) $request->input('id_real')),
            'dealer_name' => trim((string) $request->input('dealer_name')),
            'sales_name' => trim((string) $request->input('sales_name')),
            'whatsapp' => trim((string) $request->input('whatsapp')),
            'is_pkp' => $request->boolean('is_pkp', false),
        ];

        $cnFile = $request->file('credit_note_file');
        $agrFile = $request->file('agreement_file');
        $taxFile = $request->file('tax_invoice_file');

        if ($cnFile && $agrFile && $cnFile->isValid() && $agrFile->isValid()) {
            if ($cnFile->getClientOriginalName() === $agrFile->getClientOriginalName() && $cnFile->getSize() === $agrFile->getSize()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak boleh digabung! File yang diunggah untuk Credit Note dan Agreement sama persis. Harap pisahkan file dokumen khusus Credit Note saja dan Agreement saja.',
                    'error_type' => 'MERGED_DOCUMENTS',
                ], 422);
            }
        }
        if ($cnFile && $taxFile && $cnFile->isValid() && $taxFile->isValid()) {
            if ($cnFile->getClientOriginalName() === $taxFile->getClientOriginalName() && $cnFile->getSize() === $taxFile->getSize()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak boleh digabung! File yang diunggah untuk Credit Note dan Faktur Pajak sama persis. Harap pisahkan file dokumen.',
                    'error_type' => 'MERGED_DOCUMENTS',
                ], 422);
            }
        }
        if ($agrFile && $taxFile && $agrFile->isValid() && $taxFile->isValid()) {
            if ($agrFile->getClientOriginalName() === $taxFile->getClientOriginalName() && $agrFile->getSize() === $taxFile->getSize()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak boleh digabung! File yang diunggah untuk Agreement dan Faktur Pajak sama persis. Harap pisahkan file dokumen.',
                    'error_type' => 'MERGED_DOCUMENTS',
                ], 422);
            }
        }

        // 1. Inspect documents with AI first before storing or creating submission
        $inspection = $this->aiService->inspectDocumentFiles($formData, [
            'cn' => $request->file('credit_note_file') ?: $request->input('credit_note_url'),
            'agr' => $request->file('agreement_file') ?: $request->input('agreement_url'),
            'faktur' => $request->file('tax_invoice_file') ?: $request->input('tax_invoice_url'),
        ]);

        // Auto-fill or validate dealer name from CN or Agreement if input is '-' or empty
        $isDealerAuto = ($formData['dealer_name'] === '-' || $formData['dealer_name'] === '');
        if ($isDealerAuto) {
            if (! empty($inspection['dealer_name'])) {
                $formData['dealer_name'] = $inspection['dealer_name'];
            } elseif (! empty($inspection['agr_dealer_name'])) {
                $formData['dealer_name'] = $inspection['agr_dealer_name'];
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Nama dealer diisi '-', namun sistem tidak dapat membaca nama toko otomatis dari dokumen Credit Note maupun Agreement. Silakan ketik nama dealer secara manual.",
                    'error_type' => 'DEALER_NAME_REQUIRED',
                ], 422);
            }

            if (! empty($inspection['dealer_name']) && ! empty($inspection['agr_dealer_name'])) {
                if (! $this->aiService->isDealerNameMatching($inspection['dealer_name'], $inspection['agr_dealer_name'])) {
                    return response()->json([
                        'success' => false,
                        'message' => "Nama dealer pada Credit Note ('{$inspection['dealer_name']}') berbeda dengan nama dealer pada Agreement ('{$inspection['agr_dealer_name']}'). Harap pastikan kedua dokumen berasal dari dealer yang sama.",
                        'error_type' => 'DOC_DEALER_CONFLICT',
                        'doc_validation' => $inspection['doc_validation'] ?? [],
                        'cek_dokumen' => $inspection['cek_dokumen'] ?? '',
                    ], 422);
                }
            }
        } elseif (! empty($inspection['dealer_mismatch'])) {
            $extracted = $inspection['dealer_name'] ?? 'Dokumen CN';

            return response()->json([
                'success' => false,
                'message' => "Nama dealer di formulir ('{$formData['dealer_name']}') tidak sesuai dengan nama dealer pada dokumen Credit Note ('{$extracted}'). Harap sesuaikan atau isi '-' agar nama dealer otomatis diambil dari dokumen.",
                'error_type' => 'DEALER_MISMATCH',
                'doc_validation' => $inspection['doc_validation'] ?? [],
                'cek_dokumen' => $inspection['cek_dokumen'] ?? '',
            ], 422);
        }

        // Agreement Dealer Mismatch
        if (! empty($inspection['agr_dealer_mismatch'])) {
            $extractedAgrDealer = $inspection['agr_dealer_name'] ?? 'Dokumen Agreement';

            return response()->json([
                'success' => false,
                'message' => "Nama dealer di formulir ('{$formData['dealer_name']}') tidak sesuai dengan nama dealer pada dokumen Agreement ('{$extractedAgrDealer}'). Harap sesuaikan dokumen Agreement Anda.",
                'error_type' => 'AGR_DEALER_MISMATCH',
                'doc_validation' => $inspection['doc_validation'] ?? [],
                'cek_dokumen' => $inspection['cek_dokumen'] ?? '',
            ], 422);
        }

        // Agreement Program Mismatch
        if (! empty($inspection['agr_program_mismatch'])) {
            $extractedAgrProgram = $inspection['agr_program_name'] ?? 'Dokumen Agreement';

            return response()->json([
                'success' => false,
                'message' => "Nama program yang diajukan ('{$formData['program_name']}') tidak sesuai dengan nama program pada dokumen Agreement ('{$extractedAgrProgram}'). Harap sesuaikan dokumen Agreement Anda.",
                'error_type' => 'AGR_PROGRAM_MISMATCH',
                'doc_validation' => $inspection['doc_validation'] ?? [],
                'cek_dokumen' => $inspection['cek_dokumen'] ?? '',
            ], 422);
        }

        // 2. Strictly block submission if documents are swapped or invalid
        if (! empty($inspection['has_swapped'])) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen terdeteksi tidak sesuai (Credit Note & Agreement terbalik). Silakan unggah dokumen yang benar pada masing-masing kolom sebelum mengirim.',
                'error_type' => 'DOC_SWAPPED',
                'doc_validation' => $inspection['doc_validation'],
            ], 422);
        }

        if (! empty($inspection['has_invalid'])) {
            $reason = $inspection['keterangan'] ?: ($inspection['cek_dokumen'] ?: 'Dokumen tidak sesuai / tidak sah');

            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak sesuai: '.$reason.'. Formulir tidak dapat dikirim sebelum dokumen diperbaiki.',
                'error_type' => 'DOC_INVALID',
                'doc_validation' => $inspection['doc_validation'],
                'cek_dokumen' => $inspection['cek_dokumen'],
            ], 422);
        }

        if (($inspection['status_potong_purchase'] ?? '') === 'BELUM BISA POTONG') {
            $reason = $inspection['keterangan'] ?: ($inspection['cek_dokumen'] ?: 'Dokumen belum memenuhi syarat verifikasi');

            return response()->json([
                'success' => false,
                'message' => 'Pengajuan belum memenuhi syarat: '.$reason.'. Formulir tidak dapat dikirim.',
                'error_type' => 'NOT_ELIGIBLE',
                'doc_validation' => $inspection['doc_validation'],
                'cek_dokumen' => $inspection['cek_dokumen'],
            ], 422);
        }

        // 3. Documents are verified clean - store to SeaweedFS / S3 (Dealer / Program / File)
        $safeDealer = preg_replace('/[\\\\\/:\*\?"<>\|]/', '_', trim($formData['dealer_name'])) ?: 'DEALER';
        $safeProgram = preg_replace('/[\\\\\/:\*\?"<>\|]/', '_', trim($formData['program_name'])) ?: 'PROGRAM';
        $s3Folder = "{$safeDealer}/{$safeProgram}";

        $useS3 = ! empty(config('filesystems.disks.s3.key')) && ! empty(config('filesystems.disks.s3.bucket'));

        $cnUrl = $request->input('credit_note_url');
        if ($request->hasFile('credit_note_file')) {
            $file = $request->file('credit_note_file');
            $filename = 'CN_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            if ($useS3) {
                try {
                    $path = Storage::disk('s3')->putFileAs($s3Folder, $file, $filename);
                    $cnUrl = Storage::disk('s3')->url($path);
                } catch (\Throwable $e) {
                    Log::warning('Upload CN to S3 failed, fallback to local: '.$e->getMessage());
                    $path = $file->store('program_documents', 'public');
                    $cnUrl = url('storage/'.$path);
                }
            } else {
                $path = $file->store('program_documents', 'public');
                $cnUrl = url('storage/'.$path);
            }
        }

        $agrUrl = $request->input('agreement_url');
        if ($request->hasFile('agreement_file')) {
            $file = $request->file('agreement_file');
            $filename = 'AGR_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            if ($useS3) {
                try {
                    $path = Storage::disk('s3')->putFileAs($s3Folder, $file, $filename);
                    $agrUrl = Storage::disk('s3')->url($path);
                } catch (\Throwable $e) {
                    Log::warning('Upload AGR to S3 failed, fallback to local: '.$e->getMessage());
                    $path = $file->store('program_documents', 'public');
                    $agrUrl = url('storage/'.$path);
                }
            } else {
                $path = $file->store('program_documents', 'public');
                $agrUrl = url('storage/'.$path);
            }
        }

        $taxUrl = $request->input('tax_invoice_url');
        if ($request->hasFile('tax_invoice_file')) {
            $file = $request->file('tax_invoice_file');
            $filename = 'FAKTUR_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            if ($useS3) {
                try {
                    $path = Storage::disk('s3')->putFileAs($s3Folder, $file, $filename);
                    $taxUrl = Storage::disk('s3')->url($path);
                } catch (\Throwable $e) {
                    Log::warning('Upload Faktur to S3 failed, fallback to local: '.$e->getMessage());
                    $path = $file->store('program_documents', 'public');
                    $taxUrl = url('storage/'.$path);
                }
            } else {
                $path = $file->store('program_documents', 'public');
                $taxUrl = url('storage/'.$path);
            }
        }

        $timestamp = date('d/m/Y H:i:s');
        $hashString = "{$timestamp}|{$formData['region']}|{$formData['id_real']}|{$formData['dealer_name']}|{$formData['program_name']}|".uniqid();
        $rowHash = sha1($hashString);

        $financial = $inspection['financial'];

        $submission = ProgramSubmission::create([
            'submission_timestamp' => $timestamp,
            'region' => $formData['region'],
            'id_real' => $formData['id_real'],
            'dealer_name' => $formData['dealer_name'],
            'program_name' => $formData['program_name'],
            'sales_name' => $formData['sales_name'],
            'whatsapp' => $formData['whatsapp'] ?: null,
            'credit_note_url' => $cnUrl,
            'agreement_url' => $agrUrl,
            'tax_invoice_url' => $taxUrl,
            'row_hash' => $rowHash,
            'cek_dokumen' => $inspection['cek_dokumen'],
            'status_potong_purchase' => $inspection['status_potong_purchase'],
            'keterangan' => $inspection['keterangan'],
            'doc_validation' => $inspection['doc_validation'],
            'incentive' => $financial['incentive'] ?? null,
            'dpp' => $financial['dpp'] ?? null,
            'dpp_lain' => $financial['dpp_lain'] ?? 0.0,
            'ppn' => $financial['ppn'] ?? 0.0,
            'nilai_pph' => $financial['nilai_pph'] ?? null,
            'net_pay' => $financial['net_pay'] ?? null,
            'cek_pajak_tarif_pph' => $financial['cek_pajak_tarif_pph'] ?? 0.0,
            'selisih' => $financial['selisih'] ?? 0.0,
            'no_faktur' => $financial['no_faktur'] ?? null,
            'tgl_faktur' => $financial['tgl_faktur'] ?? null,
            'note_pph' => $inspection['audit']['note_pph'] ?? 'ok',
            'raw_data' => [
                'source' => 'web_form',
                'inspection' => $inspection,
                'client_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        // Otomatis push data pengajuan ke Google Spreadsheet
        $sheetResult = null;
        try {
            $sheetResult = $this->service->appendSubmissionToSpreadsheet($submission);
        } catch (\Throwable $e) {
            Log::warning('Push submission to Google Spreadsheet failed: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan Program REALME berhasil dikirim dan diverifikasi AI.',
            'id' => $submission->id,
            'submission' => $submission,
            'is_clean' => $inspection['is_clean'],
            'status_potong_purchase' => $submission->status_potong_purchase,
            'cek_dokumen' => $submission->cek_dokumen,
            'doc_validation' => $submission->doc_validation,
            'spreadsheet_pushed' => $sheetResult['success'] ?? false,
        ]);
    }
}

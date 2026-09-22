<?php

namespace App\Http\Controllers;

use App\Console\Commands\AutoAnalyzeProgramAiCommand;
use App\Models\ProgramSubmission;
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

        $cols = [
            'id', 'submission_timestamp', 'region', 'id_real', 'dealer_name',
            'program_name', 'sales_name', 'credit_note_url', 'agreement_url',
            'tax_invoice_url', 'incentive', 'dpp', 'dpp_lain', 'ppn', 'nilai_pph',
            'net_pay', 'cek_pajak_tarif_pph', 'selisih', 'note_pph', 'no_faktur',
            'tgl_faktur', 'no_po_sj', 'no_transaksi', 'tgl_input',
            'tgl_share_cn', 'lama_pending', 'keterangan', 'cek_dokumen',
            'status_potong_purchase', 'status_potong_ar', 'tgl_potong_tf', 'updated_at',
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

        return response()->json([
            'submissions' => $submissions,
            'regions' => $regions,
            'programs' => $programs,
            'status_purchase_options' => ProgramSubmission::STATUS_PURCHASE_OPTIONS,
            'keterangan_options' => ProgramSubmission::KETERANGAN_OPTIONS,
            'total_submissions' => $totalSubmissions,
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
            'program_name', 'sales_name', 'credit_note_url', 'agreement_url',
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
            'doc_validation' => 'nullable|array',
        ]);

        if (isset($validated['nilai_pph']) && isset($validated['cek_pajak_tarif_pph']) && ! array_key_exists('selisih', $validated)) {
            $validated['selisih'] = round((float) $validated['nilai_pph'] - (float) $validated['cek_pajak_tarif_pph'], 2);
        }

        $submission->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data tracking & keuangan program berhasil diperbarui.',
            'submission' => $submission,
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
     * Save Google Apps Script Web App URL config.
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $this->service->setWebAppUrl($request->input('url'));

        return response()->json([
            'success' => true,
            'message' => 'URL Google Apps Script Web App berhasil disimpan.',
            'configured_webapp_url' => $this->service->getWebAppUrl(),
        ]);
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
}

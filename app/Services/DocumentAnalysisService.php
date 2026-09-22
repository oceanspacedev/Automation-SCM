<?php

namespace App\Services;

use App\Models\ProgramSubmission;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocumentAnalysisService
{
    public const CACHE_KEY_CONFIG = 'ai_router_config';

    public const CACHE_KEY_MODELS = 'ai_router_available_models';

    /**
     * Get active AI Router config (from cache or config/services.php fallback).
     *
     * @return array{base_url: string, api_key: string, model: string}
     */
    public function getConfig(): array
    {
        $cached = Cache::get(self::CACHE_KEY_CONFIG);
        $model = (string) ($cached['model'] ?? config('services.openai_compatible.model', 'ag/gemini-3.7-flash-low'));
        if ($model === 'ag/gemini-3-flash') {
            $model = 'ag/gemini-3.7-flash-low';
        }

        if (is_array($cached) && ! empty($cached['api_key'])) {
            return [
                'base_url' => rtrim($cached['base_url'] ?? config('services.openai_compatible.base_url', 'https://router.rizqis.com/v1'), '/'),
                'api_key' => (string) ($cached['api_key'] ?? config('services.openai_compatible.api_key', '')),
                'model' => $model,
            ];
        }

        return [
            'base_url' => rtrim(config('services.openai_compatible.base_url', 'https://router.rizqis.com/v1'), '/'),
            'api_key' => (string) config('services.openai_compatible.api_key', ''),
            'model' => $model,
        ];
    }

    /**
     * Save AI Router configuration.
     */
    public function saveConfig(string $baseUrl, string $apiKey, string $model): void
    {
        $data = [
            'base_url' => rtrim(trim($baseUrl), '/'),
            'api_key' => trim($apiKey),
            'model' => trim($model),
        ];

        Cache::forever(self::CACHE_KEY_CONFIG, $data);
        Cache::forget(self::CACHE_KEY_MODELS);
    }

    /**
     * Test connection to the AI Router.
     */
    public function testConnection(?string $baseUrl = null, ?string $apiKey = null, ?string $model = null): array
    {
        $config = $this->getConfig();
        $targetUrl = rtrim($baseUrl ?: $config['base_url'], '/');
        $targetKey = $apiKey ?: $config['api_key'];
        $targetModel = $model ?: $config['model'];

        if (empty($targetKey)) {
            throw new Exception('API Key AI belum diatur.');
        }

        $response = Http::withoutVerifying()
            ->withToken($targetKey)
            ->timeout(15)
            ->get("{$targetUrl}/models");

        if (! $response->successful()) {
            throw new Exception("Koneksi gagal (HTTP {$response->status()}): ".($response->json('error.message') ?? $response->body()));
        }

        $modelsData = $response->json('data') ?? [];
        $modelIds = array_column($modelsData, 'id');

        return [
            'success' => true,
            'message' => 'Koneksi ke AI Router berhasil.',
            'total_models' => count($modelIds),
            'current_model' => $targetModel,
            'model_found' => in_array($targetModel, $modelIds),
        ];
    }

    /**
     * Fetch list of available models from AI Router.
     *
     * @return array<string>
     */
    public function getAvailableModels(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && Cache::has(self::CACHE_KEY_MODELS)) {
            return (array) Cache::get(self::CACHE_KEY_MODELS);
        }

        $config = $this->getConfig();
        if (empty($config['api_key'])) {
            return [];
        }

        try {
            $response = Http::withoutVerifying()
                ->withToken($config['api_key'])
                ->timeout(15)
                ->get("{$config['base_url']}/models");

            if ($response->successful()) {
                $modelsData = $response->json('data') ?? [];
                $modelIds = array_column($modelsData, 'id');
                sort($modelIds);
                Cache::put(self::CACHE_KEY_MODELS, $modelIds, 3600);

                return $modelIds;
            }
        } catch (Exception $e) {
            Log::warning('Gagal mengambil daftar model AI: '.$e->getMessage());
        }

        return [];
    }

    /**
     * Evaluate document completeness using business rules.
     *
     * @return array{is_complete: bool, cek_dokumen: string, status_potong_purchase: string, keterangan: string}
     */
    public function evaluateCompleteness(ProgramSubmission $submission): array
    {
        $hasCn = ! empty($submission->credit_note_url) && str_starts_with(trim((string) $submission->credit_note_url), 'http');
        $hasAgr = ! empty($submission->agreement_url) && str_starts_with(trim((string) $submission->agreement_url), 'http');
        $hasFaktur = ! empty($submission->tax_invoice_url) && str_starts_with(trim((string) $submission->tax_invoice_url), 'http');

        if ($hasCn && $hasAgr && $hasFaktur) {
            return [
                'is_complete' => true,
                'cek_dokumen' => 'LENGKAP',
                'status_potong_purchase' => 'BISA DI POTONG',
                'keterangan' => 'Semua dokumen (Credit Note, Agreement, dan Faktur Pajak) lengkap dan siap diproses potong.',
            ];
        }

        $missing = [];
        if (! $hasCn) {
            $missing[] = 'CN';
        }
        if (! $hasAgr) {
            $missing[] = 'AGR';
        }
        if (! $hasFaktur) {
            $missing[] = 'FAKTUR';
        }

        if (count($missing) === 3) {
            $cek = 'SEMUA DOKUMEN BELUM ADA';
        } elseif (count($missing) === 2) {
            $cek = implode(' & ', $missing).' BELUM ADA';
        } else {
            $cek = $missing[0].' BELUM ADA';
        }

        return [
            'is_complete' => false,
            'cek_dokumen' => $cek,
            'status_potong_purchase' => 'BELUM BISA POTONG',
            'keterangan' => 'Dokumen belum lengkap ('.$cek.'). Harap lengkapi dokumen sebelum diproses potong.',
        ];
    }

    /**
     * Analyze a single ProgramSubmission row with AI and update tracking columns.
     *
     * @return array{
     *     submission: ProgramSubmission,
    /**
     * Download a document from Google Drive or direct URL and convert to base64 data URI.
     */
    public function fetchDocumentAsDataUri(?string $url): ?string
    {
        if (empty($url) || ! str_starts_with(trim($url), 'http')) {
            return null;
        }

        $url = trim($url);
        $downloadUrl = $url;

        // Check if it's a Google Drive link
        if (preg_match('/(?:id=|\/d\/)([a-zA-Z0-9_-]{20,})/', $url, $matches)) {
            $fileId = $matches[1];
            $downloadUrl = "https://drive.google.com/uc?export=download&id={$fileId}";
        }

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->timeout(15)
                ->get($downloadUrl);

            if (! $response->successful()) {
                return null;
            }

            $bytes = $response->body();
            if (empty($bytes) || strlen($bytes) > 20 * 1024 * 1024) {
                return null;
            }

            // Check if returned HTML error page instead of binary
            if (str_starts_with(trim($bytes), '<!DOCTYPE') || str_starts_with(trim($bytes), '<html')) {
                return null;
            }

            if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
                $mime = 'image/jpeg';
            } elseif (str_starts_with($bytes, "\x89PNG")) {
                $mime = 'image/png';
            } elseif (str_starts_with($bytes, '%PDF')) {
                $mime = 'application/pdf';
            } elseif (str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 16), 'WEBP')) {
                $mime = 'image/webp';
            } else {
                $contentType = $response->header('Content-Type');
                $mime = ! empty($contentType) && ! str_contains($contentType, 'octet-stream')
                    ? explode(';', $contentType)[0]
                    : 'image/jpeg';
            }

            return "data:{$mime};base64,".base64_encode($bytes);
        } catch (\Throwable $e) {
            Log::warning("Gagal mengunduh dokumen dari {$url}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Analyze a single ProgramSubmission row with AI and update tracking columns.
     *
     * @return array{
     *     submission: ProgramSubmission,
     *     cek_dokumen: string,
     *     status_potong_purchase: string,
     *     keterangan: string,
     *     raw_analysis: array
     * }
     */
    public function analyzeSubmission(ProgramSubmission $submission): array
    {
        $config = $this->getConfig();
        $fallback = $this->evaluateCompleteness($submission);
        $parsed = null;

        if (! empty($config['api_key'])) {
            try {
                // Build multimodal content (Text + Image/PDF parts)
                $userParts = [
                    [
                        'type' => 'text',
                        'text' => $this->buildUserPrompt($submission),
                    ],
                ];

                // Fetch and attach Credit Note document if available
                if ($cnDataUri = $this->fetchDocumentAsDataUri($submission->credit_note_url)) {
                    $userParts[] = [
                        'type' => 'text',
                        'text' => '--- LAMPIRAN DOKUMEN CREDIT NOTE (CN) ---',
                    ];
                    $userParts[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $cnDataUri,
                        ],
                    ];
                }

                // Fetch and attach Faktur Pajak document if available
                if ($taxDataUri = $this->fetchDocumentAsDataUri($submission->tax_invoice_url)) {
                    $userParts[] = [
                        'type' => 'text',
                        'text' => '--- LAMPIRAN DOKUMEN FAKTUR PAJAK ---',
                    ];
                    $userParts[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $taxDataUri,
                        ],
                    ];
                }

                // Fetch and attach Agreement document if available
                if ($agrDataUri = $this->fetchDocumentAsDataUri($submission->agreement_url)) {
                    $userParts[] = [
                        'type' => 'text',
                        'text' => '--- LAMPIRAN DOKUMEN AGREEMENT (AGR) ---',
                    ];
                    $userParts[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $agrDataUri,
                        ],
                    ];
                }

                $payload = [
                    'model' => $config['model'] ?: 'ag/gemini-3-flash',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getSystemPrompt(),
                        ],
                        [
                            'role' => 'user',
                            'content' => $userParts,
                        ],
                    ],
                    'stream' => false,
                    'temperature' => 0.1,
                ];

                $response = Http::withoutVerifying()
                    ->withToken($config['api_key'])
                    ->timeout(45)
                    ->post("{$config['base_url']}/chat/completions", $payload);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');
                    if (! empty($content)) {
                        $parsed = $this->parseJsonResponse($content);
                    }
                } else {
                    $errorMsg = $response->json('error.message') ?? $response->body();
                    Log::warning("AI Router returned HTTP {$response->status()} for submission ID {$submission->id}: {$errorMsg}");
                }
            } catch (Exception $e) {
                Log::warning("AI Router call error for submission ID {$submission->id}, using rule evaluation: ".$e->getMessage());
            }
        }

        $financialData = [];
        if (is_array($parsed)) {
            // Extract or calculate financial and tax values
            $dpp = isset($parsed['dpp']) && is_numeric($parsed['dpp']) ? (float) $parsed['dpp'] : $submission->dpp;
            $dppLain = isset($parsed['dpp_lain']) && is_numeric($parsed['dpp_lain']) ? (float) $parsed['dpp_lain'] : ($submission->dpp_lain ?? 0);
            $ppn = isset($parsed['ppn']) && is_numeric($parsed['ppn']) ? (float) $parsed['ppn'] : ($submission->ppn ?? 0);
            $nilaiPph = isset($parsed['nilai_pph']) && is_numeric($parsed['nilai_pph']) ? (float) $parsed['nilai_pph'] : $submission->nilai_pph;
            $netPay = isset($parsed['net_pay']) && is_numeric($parsed['net_pay']) ? (float) $parsed['net_pay'] : $submission->net_pay;

            $incentive = isset($parsed['incentive']) && is_numeric($parsed['incentive'])
                ? (float) $parsed['incentive']
                : (isset($parsed['total']) && is_numeric($parsed['total']) ? (float) $parsed['total'] : $submission->incentive);

            // Reconstruct Gross Incentive:
            // - Untuk dealer PKP (ada PPN): INCENTIVE = DPP + PPN
            // - Untuk dealer Non-PKP (tanpa PPN): INCENTIVE = DPP * 1.11 (dibulatkan ke ribuan terdekat)
            $hasPpn = ! empty($ppn) && (float) $ppn > 0;
            $hasFaktur = (! empty($parsed['no_faktur']) && trim((string) $parsed['no_faktur']) !== '-') ||
                         (! empty($submission->tax_invoice_url) && trim((string) $submission->tax_invoice_url) !== '-' && str_starts_with(trim((string) $submission->tax_invoice_url), 'http'));
            $isNonPkp = ! $hasPpn && ! $hasFaktur;

            if ($dpp !== null && ($incentive === null || abs($incentive - $dpp) < 0.01)) {
                if ($hasPpn) {
                    $incentive = (float) round($dpp + $ppn);
                } else {
                    $grossCandidate = round($dpp * 1.11);
                    if (abs($grossCandidate - round($grossCandidate, -3)) <= 15) {
                        $incentive = (float) round($grossCandidate, -3);
                    } else {
                        $incentive = (float) $grossCandidate;
                    }
                }
            } elseif ($incentive === null && $dpp !== null) {
                $incentive = $dpp;
            }

            if ($dpp === null && $incentive !== null) {
                $dpp = $incentive;
            }

            // Auto-calculate Net Pay if missing: Net Pay = DPP + PPN - Nilai PPh
            if ($netPay === null && $dpp !== null) {
                $netPay = round($dpp + ($ppn ?? 0) - ($nilaiPph ?? 0), 2);
            }

            // Deteksi Tarif PPh (2.0% vs 2.5%):
            // 1. Jika nilai_pph sudah ada, cek apakah potongan mendekati 2.0% atau 2.5% dari DPP
            $detectedRate = null;
            if ($dpp !== null && $dpp > 0 && $nilaiPph !== null) {
                $ratio = $nilaiPph / $dpp;
                if (abs($ratio - 0.02) <= 0.0025) {
                    $detectedRate = 0.02; // Tarif PPh 23 (2.0%)
                } elseif (abs($ratio - 0.025) <= 0.0025) {
                    $detectedRate = 0.025; // Tarif PPh 21 (2.5%)
                }
            }

            // 2. Jika belum bisa dideteksi dari nilai_pph, lihat dari status PPN/Faktur:
            //    Ada PPN (PKP/Badan Usaha) => default 2.0% (PPh 23)
            //    Tidak ada PPN (Non-PKP / Perorangan) => default 2.5% (PPh 21)
            if ($detectedRate === null) {
                $detectedRate = $hasPpn ? 0.02 : 0.025;
            }

            // Cek Pajak Tarif PPh
            if (isset($parsed['cek_pajak_tarif_pph']) && is_numeric($parsed['cek_pajak_tarif_pph'])) {
                $cekPajak = (float) $parsed['cek_pajak_tarif_pph'];
            } elseif ($submission->cek_pajak_tarif_pph !== null) {
                $cekPajak = $submission->cek_pajak_tarif_pph;
            } elseif ($dpp !== null) {
                $cekPajak = round($dpp * $detectedRate, 2);
            } else {
                $cekPajak = null;
            }

            // Calculate Selisih (0 jika perbedaan hanya toleransi pembulatan <= 1 rupiah)
            if ($nilaiPph !== null && $cekPajak !== null) {
                $diff = round($nilaiPph - $cekPajak, 2);
                $selisih = abs($diff) <= 1 ? 0.0 : $diff;
            } else {
                $selisih = $submission->selisih;
            }

            // Physical audit status (CAP?, TTD?, NPWP?, ok)
            $hasStamp = $parsed['has_stamp'] ?? true;
            $hasSignature = $parsed['has_signature'] ?? true;
            $hasNpwp = $parsed['has_npwp'] ?? true;

            $noteParts = [];
            if (! $hasNpwp) {
                $noteParts[] = 'NPWP?';
            }
            if (! $hasStamp) {
                $noteParts[] = 'CAP?';
            }
            if (! $hasSignature) {
                $noteParts[] = 'TTD?';
            }

            if (! empty($parsed['note_pph'])) {
                $notePph = trim((string) $parsed['note_pph']);
            } elseif (! empty($noteParts)) {
                $notePph = implode(' ', $noteParts);
            } else {
                $notePph = ($submission->credit_note_url || $submission->tax_invoice_url) ? 'ok' : null;
            }

            $noFaktur = ! empty($parsed['no_faktur']) ? trim((string) $parsed['no_faktur']) : $submission->no_faktur;
            $tglFaktur = ! empty($parsed['tgl_faktur']) ? trim((string) $parsed['tgl_faktur']) : $submission->tgl_faktur;

            // Process document classification & swap detection
            // Track which slots have actual URLs uploaded
            $slotHasUrl = [
                'cn' => ! empty($submission->credit_note_url) && str_starts_with(trim((string) $submission->credit_note_url), 'http'),
                'agr' => ! empty($submission->agreement_url) && str_starts_with(trim((string) $submission->agreement_url), 'http'),
                'faktur' => ! empty($submission->tax_invoice_url) && str_starts_with(trim((string) $submission->tax_invoice_url), 'http'),
            ];

            $missingDocs = [];
            if (! $slotHasUrl['cn']) {
                $missingDocs[] = 'CN';
            }
            if (! $slotHasUrl['agr']) {
                $missingDocs[] = 'AGR';
            }
            if (! $slotHasUrl['faktur']) {
                $missingDocs[] = 'FAKTUR';
            }

            // Process document classification & swap detection
            $docValidation = $submission->doc_validation;
            if (isset($parsed['doc_validation']) && is_array($parsed['doc_validation'])) {
                $cleanValidation = [];
                foreach (['cn', 'agr', 'faktur'] as $slot) {
                    if (! $slotHasUrl[$slot]) {
                        // Slot is not uploaded => status is 'empty', NEVER 'invalid' or 'swapped'
                        $cleanValidation[$slot] = [
                            'status' => 'empty',
                            'actual_type' => 'none',
                            'message' => 'Dokumen belum diunggah',
                        ];

                        continue;
                    }

                    if (isset($parsed['doc_validation'][$slot]) && is_array($parsed['doc_validation'][$slot])) {
                        $rawSlot = $parsed['doc_validation'][$slot];
                        $status = in_array($rawSlot['status'] ?? '', ['valid', 'swapped', 'invalid', 'empty'], true)
                            ? $rawSlot['status']
                            : 'valid';
                        $cleanValidation[$slot] = [
                            'status' => $status,
                            'actual_type' => (string) ($rawSlot['actual_type'] ?? $slot),
                            'message' => (string) ($rawSlot['message'] ?? ''),
                        ];
                    } else {
                        $cleanValidation[$slot] = [
                            'status' => 'valid',
                            'actual_type' => $slot,
                            'message' => 'Dokumen diunggah',
                        ];
                    }
                }
                $docValidation = $cleanValidation;
            } else {
                $docValidation = [];
                foreach (['cn', 'agr', 'faktur'] as $slot) {
                    $docValidation[$slot] = [
                        'status' => $slotHasUrl[$slot] ? 'valid' : 'empty',
                        'actual_type' => $slotHasUrl[$slot] ? $slot : 'none',
                        'message' => $slotHasUrl[$slot] ? 'Dokumen diunggah' : 'Dokumen belum diunggah',
                    ];
                }
            }

            // Check if any UPLOADED document is swapped or invalid
            $hasSwapped = false;
            $hasInvalid = false;
            $swapDetails = [];
            $invalidDetails = [];

            if (is_array($docValidation)) {
                foreach ($docValidation as $slot => $info) {
                    // Only uploaded slots can be considered swapped or invalid
                    if (! empty($slotHasUrl[$slot])) {
                        if (($info['status'] ?? '') === 'swapped') {
                            $hasSwapped = true;
                            $swapDetails[] = strtoupper($slot).' ('.strtoupper($info['actual_type'] ?? '').')';
                        } elseif (($info['status'] ?? '') === 'invalid') {
                            $hasInvalid = true;
                            $invalidDetails[] = strtoupper($slot);
                        }
                    }
                }
            }

            $financialData = [
                'incentive' => $incentive,
                'dpp' => $dpp,
                'dpp_lain' => $dppLain,
                'ppn' => $ppn,
                'nilai_pph' => $nilaiPph,
                'net_pay' => $netPay,
                'cek_pajak_tarif_pph' => $cekPajak,
                'selisih' => $selisih,
                'note_pph' => $notePph,
                'no_faktur' => $noFaktur,
                'tgl_faktur' => $tglFaktur,
                'doc_validation' => $docValidation,
            ];
        }

        if (is_array($parsed) && ! empty($parsed['cek_dokumen'])) {
            $cekDokumen = trim((string) $parsed['cek_dokumen']);
            $statusPurchase = trim((string) ($parsed['status_potong_purchase'] ?? ''));

            if ($hasSwapped) {
                $statusPurchase = 'BELUM BISA POTONG';
                if (! str_contains(strtoupper($cekDokumen), 'TERTUKAR')) {
                    $cekDokumen = 'DOKUMEN TERTUKAR ('.implode(', ', $swapDetails).')';
                }
            } elseif ($hasInvalid) {
                $statusPurchase = 'BELUM BISA POTONG';
                if (! str_contains(strtoupper($cekDokumen), 'TIDAK SESUAI') && ! str_contains(strtoupper($cekDokumen), 'SALAH')) {
                    $cekDokumen = 'DOKUMEN TIDAK SESUAI ('.implode(', ', $invalidDetails).')';
                }
            } elseif (! empty($missingDocs)) {
                $statusPurchase = 'BELUM BISA POTONG';
                if (count($missingDocs) === 3) {
                    $cekDokumen = 'SEMUA DOKUMEN BELUM ADA';
                } elseif (count($missingDocs) === 2) {
                    $cekDokumen = implode(' & ', $missingDocs).' BELUM ADA';
                } else {
                    $cekDokumen = $missingDocs[0].' BELUM ADA';
                }
            }

            if (! in_array($statusPurchase, ProgramSubmission::STATUS_PURCHASE_OPTIONS, true)) {
                $statusPurchase = ($parsed['is_complete'] ?? false) && ! $hasSwapped && ! $hasInvalid && empty($missingDocs)
                    ? 'BISA DI POTONG'
                    : 'BELUM BISA POTONG';
            }
            $keterangan = trim((string) ($parsed['keterangan'] ?? ''));
        } else {
            $cekDokumen = $fallback['cek_dokumen'];
            $statusPurchase = $fallback['status_potong_purchase'];
            $keterangan = $fallback['keterangan'];
            $parsed = $fallback;
        }

        // Automatically update the submission
        $updatePayload = array_merge([
            'cek_dokumen' => $cekDokumen,
            'status_potong_purchase' => $statusPurchase,
        ], $financialData);

        $submission->update($updatePayload);

        return [
            'submission' => $submission->fresh(),
            'cek_dokumen' => $cekDokumen,
            'status_potong_purchase' => $statusPurchase,
            'keterangan' => $submission->keterangan,
            'ai_keterangan' => $keterangan,
            'financial' => $financialData,
            'raw_analysis' => $parsed,
        ];
    }

    /**
     * Analyze multiple submissions in batch.
     *
     * @param  array<int|string>  $submissionIds
     * @return array{
     *     total: int,
     *     success_count: int,
     *     error_count: int,
     *     results: array
     * }
     */
    public function analyzeBatch(array $submissionIds): array
    {
        $total = count($submissionIds);
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        ProgramSubmission::whereIn('id', $submissionIds)
            ->chunkById(100, function ($submissions) use (&$results, &$successCount, &$errorCount, $total) {
                foreach ($submissions as $sub) {
                    try {
                        $res = $this->analyzeSubmission($sub);
                        if ($total <= 100) {
                            $results[] = [
                                'id' => $sub->id,
                                'dealer_name' => $sub->dealer_name,
                                'success' => true,
                                'cek_dokumen' => $res['cek_dokumen'],
                                'status_potong_purchase' => $res['status_potong_purchase'],
                                'keterangan' => $res['keterangan'],
                            ];
                        }
                        $successCount++;
                    } catch (Exception $e) {
                        if ($total <= 100) {
                            $results[] = [
                                'id' => $sub->id,
                                'dealer_name' => $sub->dealer_name,
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                        $errorCount++;
                    }
                }
            });

        return [
            'total' => $total,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Build the system prompt with strict business rules.
     */
    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah sistem AI Analisis Dokumen Finansial & Operasional SCM khusus Program REALME.
Tugasmu adalah menganalisis kelengkapan tiga dokumen pendukung klaim program dealer:
1. Credit Note (CN)
2. Agreement (Agr)
3. Faktur Pajak (Faktur)

Aturan Evaluasi:
- Dokumen dianggap ADA jika URL dokumen tidak kosong dan diawali "http://" atau "https://".
- Dokumen dianggap KOSONG jika URL bernilai kosong, "-", "null", atau tidak valid.

Analisis Finansial & Pajak:
- Ekstrak atau hitung nilai angka sesuai aturan SCM:
  * incentive: Nilai kotor insentif program yang dijanjikan.
    - Untuk dealer PKP (ada Faktur Pajak / PPN): Incentive = DPP + PPN (contoh: DPP 2.934.189 + PPN 322.761 = Incentive 3.256.950).
    - Untuk dealer Non-PKP (PPN 0% / tidak ada Faktur Pajak): Incentive = DPP x 1.11 dibulatkan ke ribuan terdekat (contoh: DPP 450.450 -> Incentive 500.000).
  * dpp: Dasar Pengenaan Pajak (DPP yang tercetak di lembar CN atau Faktur)
  * dpp_lain: DPP Nilai Lain (jika "-" atau kosong isi 0)
  * ppn: Pajak Pertambahan Nilai (PPN, jika "-" atau kosong isi 0)
  * nilai_pph: Potongan PPh (tertulis PPH di CN)
  * net_pay: Nilai bersih yang dibayarkan ke dealer (DPP + PPN - PPh)
  * cek_pajak_tarif_pph: Hitung tarif pajak standar sesuai status:
    - Jika tertera tarif 2.0% (PPh 23 untuk Badan/PKP): DPP x 2.0%
    - Jika tertera tarif 2.5% (PPh 21 untuk Non-PKP/Orang Pribadi): DPP x 2.5%
  * no_faktur: Nomor Faktur Pajak jika ditemukan (format: 010.xxx-xx.xxxxxxxx)
  * tgl_faktur: Tanggal Faktur Pajak (format: DD/MM/YYYY)
- Audit Fisik / Kelengkapan Dokumen:
  * has_stamp: boolean (apakah ada stempel/cap basah atau digital dari dealer/perusahaan)
  * has_signature: boolean (apakah dokumen ditandatangani)
  * has_npwp: boolean (apakah NPWP tertera atau valid)
  * note_pph: Catatan ringkas status fisik: "ok" jika lengkap dan ada cap/TTD/NPWP, atau sebutkan yang kurang seperti "CAP?", "TTD?", "NPWP?", "CAP? TTD?", dsb.

- Validasi Isi Dokumen (Deteksi Dokumen Tertukar atau Tidak Sesuai):
  Status per slot ("valid", "swapped", "invalid", "empty"):
  - "empty": Slot dokumen TIDAK diunggah (URL kosong, "-", atau tidak valid). JANGAN PERNAH menandai dokumen kosong sebagai "invalid" atau "swapped"!
  - "valid": Dokumen ada diunggah, sesuai jenis slotnya dan data dealer/program cocok.
  - "swapped": Dokumen ada diunggah, tetapi isinya tertukar antar slot (contoh: slot Faktur diisi file CN atau Agr).
  - "invalid": Dokumen ada diunggah, tetapi isinya salah upload (contoh: foto selfie, nota sembarangan, dokumen dealer lain, dsb).

Ketentuan Penentuan Output:
1. Jika KETIGA dokumen LENGKAP dan SEMUA VALID (tidak ada yang tertukar/invalid/empty):
   - "is_complete": true
   - "cek_dokumen": "LENGKAP"
   - "status_potong_purchase": "BISA DI POTONG"
   - "keterangan": "Semua dokumen lengkap dan valid, siap diproses potong."

2. PENTING - JIKA ADA DOKUMEN YANG BELUM DIUNGGAH / KOSONG (URL kosong atau bernilai "-"):
   - Slot tersebut diberi status "empty" (BUKAN "invalid"!).
   - "is_complete": false
   - "cek_dokumen": Wajib menyebutkan dokumen yang belum ada dalam format berikut:
     * Jika hanya Faktur belum ada: "FAKTUR BELUM ADA"
     * Jika Agr dan Faktur belum ada: "AGR & FAKTUR BELUM ADA"
     * Jika hanya Agr belum ada: "AGR BELUM ADA"
     * Jika hanya CN belum ada: "CN BELUM ADA"
     * Jika semua dokumen tidak ada: "SEMUA DOKUMEN BELUM ADA"
   - "status_potong_purchase": "BELUM BISA POTONG"
   - "keterangan": Penjelasan ringkas dokumen mana yang belum diunggah.

3. DOKUMEN TERTUKAR (swapped):
   (HANYA berlaku jika ADA file yang diunggah tetapi tertukar posisi antar slot):
   - "is_complete": false
   - "cek_dokumen": Sebutkan dokumen yang tertukar, contoh: "DOKUMEN TERTUKAR (CN ↔ AGR)"
   - "status_potong_purchase": "BELUM BISA POTONG"
   - "keterangan": "File dokumen tertukar antar kolom. Harap perbaiki posisi upload dokumen."

4. DOKUMEN TIDAK SESUAI (invalid):
   (HANYA berlaku jika ADA file yang diunggah pada slot tersebut tetapi isinya salah upload / bukan dokumen resmi yang diminta):
   - "is_complete": false
   - "cek_dokumen": Sebutkan dokumen yang salah upload, contoh: "DOKUMEN TIDAK SESUAI (FAKTUR)"
   - "status_potong_purchase": "BELUM BISA POTONG"
   - "keterangan": "File yang diunggah pada slot tersebut bukan dokumen resmi yang diminta."

Format Keluaran:
Wajib mengembalikan JSON murni TANPA pembungkus markdown ```json ``` dengan key berikut:
{
  "is_complete": boolean,
  "cek_dokumen": string,
  "status_potong_purchase": "BISA DI POTONG" | "BELUM BISA POTONG",
  "keterangan": string,
  "incentive": number | null,
  "dpp": number | null,
  "dpp_lain": number,
  "ppn": number,
  "nilai_pph": number | null,
  "net_pay": number | null,
  "cek_pajak_tarif_pph": number | null,
  "has_stamp": boolean,
  "has_signature": boolean,
  "has_npwp": boolean,
  "note_pph": string,
  "no_faktur": string | null,
  "tgl_faktur": string | null,
  "doc_validation": {
    "cn": { "status": "valid" | "swapped" | "invalid" | "empty", "actual_type": "cn" | "agr" | "faktur" | "other" | "none", "message": string },
    "agr": { "status": "valid" | "swapped" | "invalid" | "empty", "actual_type": "cn" | "agr" | "faktur" | "other" | "none", "message": string },
    "faktur": { "status": "valid" | "swapped" | "invalid" | "empty", "actual_type": "cn" | "agr" | "faktur" | "other" | "none", "message": string }
  }
}
PROMPT;
    }

    /**
     * Build user prompt with submission data.
     */
    protected function buildUserPrompt(ProgramSubmission $submission): string
    {
        return json_encode([
            'id_real' => $submission->id_real ?: '-',
            'dealer_name' => $submission->dealer_name ?: '-',
            'program_name' => $submission->program_name ?: '-',
            'sales_name' => $submission->sales_name ?: '-',
            'credit_note_url' => $submission->credit_note_url ?: '',
            'agreement_url' => $submission->agreement_url ?: '',
            'tax_invoice_url' => $submission->tax_invoice_url ?: '',
            'incentive' => $submission->incentive,
            'dpp' => $submission->dpp,
            'net_pay' => $submission->net_pay,
            'status_saat_ini' => $submission->status_potong_purchase ?: 'Belum ditentukan',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Parse raw string from AI into an associative array safely.
     */
    protected function parseJsonResponse(string $raw): array
    {
        $cleaned = trim($raw);

        // Remove markdown backticks if returned
        if (str_starts_with($cleaned, '```json')) {
            $cleaned = substr($cleaned, 7);
        } elseif (str_starts_with($cleaned, '```')) {
            $cleaned = substr($cleaned, 3);
        }

        if (str_ends_with($cleaned, '```')) {
            $cleaned = substr($cleaned, 0, -3);
        }

        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Fallback: search for first { and last }
        $start = strpos($cleaned, '{');
        $end = strrpos($cleaned, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $sub = substr($cleaned, $start, $end - $start + 1);
            $decoded = json_decode($sub, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new Exception("Gagal mem-parsing format respons JSON dari AI: {$raw}");
    }
}

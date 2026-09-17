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

        if (is_array($cached) && ! empty($cached['api_key'])) {
            return [
                'base_url' => rtrim($cached['base_url'] ?? config('services.openai_compatible.base_url', 'https://router.rizqis.com/v1'), '/'),
                'api_key' => (string) ($cached['api_key'] ?? config('services.openai_compatible.api_key', '')),
                'model' => (string) ($cached['model'] ?? config('services.openai_compatible.model', 'ag/gemini-3-flash')),
            ];
        }

        return [
            'base_url' => rtrim(config('services.openai_compatible.base_url', 'https://router.rizqis.com/v1'), '/'),
            'api_key' => (string) config('services.openai_compatible.api_key', ''),
            'model' => (string) config('services.openai_compatible.model', 'ag/gemini-3-flash'),
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

        if (empty($config['api_key'])) {
            throw new Exception('API Key AI Router belum diatur. Silakan masukkan API Key di Pengaturan AI.');
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
                    'content' => $this->buildUserPrompt($submission),
                ],
            ],
            'stream' => false,
            'temperature' => 0.1,
        ];

        $response = Http::withoutVerifying()
            ->withToken($config['api_key'])
            ->timeout(30)
            ->post("{$config['base_url']}/chat/completions", $payload);

        if (! $response->successful()) {
            $errorMsg = $response->json('error.message') ?? $response->body();
            throw new Exception("AI Router error (HTTP {$response->status()}): {$errorMsg}");
        }

        $content = $response->json('choices.0.message.content');
        if (empty($content)) {
            throw new Exception('AI Router tidak mengembalikan konten respons.');
        }

        $parsed = $this->parseJsonResponse($content);

        // Sanitize status potong purchase
        $statusPurchase = trim((string) ($parsed['status_potong_purchase'] ?? ''));
        if (! in_array($statusPurchase, ProgramSubmission::STATUS_PURCHASE_OPTIONS, true)) {
            // Fallback according to completeness
            $statusPurchase = ($parsed['is_complete'] ?? false) ? 'BISA DI POTONG' : 'BELUM BISA POTONG';
        }

        $cekDokumen = trim((string) ($parsed['cek_dokumen'] ?? ''));
        $keterangan = trim((string) ($parsed['keterangan'] ?? ''));

        // Automatically update the submission (preserve keterangan for pending/aging 30 days status)
        $submission->update([
            'cek_dokumen' => $cekDokumen,
            'status_potong_purchase' => $statusPurchase,
        ]);

        return [
            'submission' => $submission->fresh(),
            'cek_dokumen' => $cekDokumen,
            'status_potong_purchase' => $statusPurchase,
            'keterangan' => $submission->keterangan,
            'ai_keterangan' => $keterangan,
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
        $submissions = ProgramSubmission::whereIn('id', $submissionIds)->get();
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($submissions as $sub) {
            try {
                $res = $this->analyzeSubmission($sub);
                $results[] = [
                    'id' => $sub->id,
                    'dealer_name' => $sub->dealer_name,
                    'success' => true,
                    'cek_dokumen' => $res['cek_dokumen'],
                    'status_potong_purchase' => $res['status_potong_purchase'],
                    'keterangan' => $res['keterangan'],
                ];
                $successCount++;
            } catch (Exception $e) {
                $results[] = [
                    'id' => $sub->id,
                    'dealer_name' => $sub->dealer_name,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                $errorCount++;
            }
        }

        return [
            'total' => count($submissions),
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

Ketentuan Penentuan Output:
1. Jika KETIGA dokumen (CN, Agr, Faktur) LENGKAP:
   - "is_complete": true
   - "cek_dokumen": "LENGKAP"
   - "status_potong_purchase": "BISA DI POTONG"
   - "keterangan": "Semua dokumen (Credit Note, Agreement, dan Faktur Pajak) lengkap dan siap diproses potong."

2. Jika ADA dokumen yang KURANG atau KOSONG:
   - "is_complete": false
   - "cek_dokumen": Sebutkan dokumen yang belum ada dalam huruf kapital singkat, contoh:
     * Jika Agr dan Faktur tidak ada: "AGR & FAKTUR BELUM ADA"
     * Jika hanya Faktur tidak ada: "FAKTUR BELUM ADA"
     * Jika hanya Agr tidak ada: "AGR BELUM ADA"
     * Jika hanya CN tidak ada: "CN BELUM ADA"
     * Jika semua tidak ada: "SEMUA DOKUMEN KOSONG"
   - "status_potong_purchase": "BELUM BISA POTONG"
   - "keterangan": Penjelasan ringkas dokumen mana saja yang wajib diunggah agar klaim dapat diproses potong.

Format Keluaran:
Wajib mengembalikan JSON murni TANPA pembungkus markdown ```json ``` dengan key berikut:
{
  "is_complete": boolean,
  "cek_dokumen": string,
  "status_potong_purchase": "BISA DI POTONG" | "BELUM BISA POTONG",
  "keterangan": string
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

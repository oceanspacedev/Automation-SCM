<?php

namespace App\Services;

use App\Models\ProgramSubmission;
use Exception;
use Illuminate\Http\UploadedFile;
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

        $isPkp = ((float) ($submission->ppn ?? 0) > 0) || app(ProgramReconciliationService::class)->isPkpFromSubmission($submission);

        $missing = [];
        if (! $hasCn) {
            $missing[] = 'CN';
        }
        if (! $hasAgr) {
            $missing[] = 'AGR';
        }
        if ($isPkp && ! $hasFaktur) {
            $missing[] = 'FAKTUR';
        }

        if (empty($missing)) {
            return [
                'is_complete' => true,
                'cek_dokumen' => 'LENGKAP',
                'status_potong_purchase' => 'BISA DI POTONG',
                'keterangan' => $isPkp
                    ? 'Semua dokumen (Credit Note, Agreement, dan Faktur Pajak) lengkap dan siap diproses potong.'
                    : 'Dokumen (Credit Note dan Agreement) lengkap untuk dealer Non PKP dan siap diproses potong.',
            ];
        }

        $totalExpected = $isPkp ? 3 : 2;
        if (count($missing) === $totalExpected) {
            $cek = 'SEMUA DOKUMEN BELUM ADA';
        } elseif (count($missing) >= 2) {
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
     * Convert an UploadedFile, local storage path, data URI, or URL to a base64 data URI string.
     */
    public function fileOrUrlToDataUri(mixed $fileOrUrl): ?string
    {
        if (empty($fileOrUrl)) {
            return null;
        }

        if ($fileOrUrl instanceof UploadedFile) {
            if (! $fileOrUrl->isValid()) {
                return null;
            }
            $bytes = file_get_contents($fileOrUrl->getRealPath());
            if (empty($bytes)) {
                return null;
            }
            $mime = $fileOrUrl->getMimeType() ?: 'application/octet-stream';

            return "data:{$mime};base64,".base64_encode($bytes);
        }

        if (is_string($fileOrUrl)) {
            $trimmed = trim($fileOrUrl);
            if (str_starts_with($trimmed, 'data:')) {
                return $trimmed;
            }

            if (str_starts_with($trimmed, '/storage/') || str_starts_with($trimmed, 'storage/')) {
                $localPath = public_path(ltrim($trimmed, '/'));
                if (file_exists($localPath)) {
                    $bytes = file_get_contents($localPath);
                    if ($bytes) {
                        $mime = mime_content_type($localPath) ?: 'application/pdf';

                        return "data:{$mime};base64,".base64_encode($bytes);
                    }
                }
            }

            return $this->fetchDocumentAsDataUri($trimmed);
        }

        return null;
    }

    /**
     * Extract readable plain text from PDF binary data.
     */
    public function extractPdfText(string $binary): string
    {
        $text = '';

        if (preg_match_all('/\((.*?)\)\s*Tj/s', $binary, $matches)) {
            $text .= implode(' ', $matches[1]).' ';
        }
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $binary, $matches)) {
            foreach ($matches[1] as $chunk) {
                if (preg_match_all('/\((.*?)\)/s', $chunk, $sub)) {
                    $text .= implode(' ', $sub[1]).' ';
                }
            }
        }

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $binary, $streams)) {
            foreach ($streams[1] as $stream) {
                $decomp = @gzuncompress($stream);
                if ($decomp) {
                    if (preg_match_all('/\((.*?)\)\s*Tj/s', $decomp, $m)) {
                        $text .= implode(' ', $m[1]).' ';
                    }
                    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decomp, $m)) {
                        foreach ($m[1] as $chunk) {
                            if (preg_match_all('/\((.*?)\)/s', $chunk, $sub)) {
                                $text .= implode(' ', $sub[1]).' ';
                            }
                        }
                    }
                }
            }
        }

        $text = str_replace(['\\(', '\\)', '\\\\', "\r", "\n"], ['(', ')', '\\', ' ', ' '], $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Extract name, mime, text, and binary details from a file or URL.
     *
     * @return array{name: string, mime: string, text: string, data_uri: ?string, has_file: bool}
     */
    public function extractFileContentInfo(mixed $fileOrUrl): array
    {
        $result = [
            'name' => '',
            'mime' => '',
            'text' => '',
            'data_uri' => null,
            'has_file' => false,
        ];

        if (empty($fileOrUrl)) {
            return $result;
        }

        if ($fileOrUrl instanceof UploadedFile) {
            if ($fileOrUrl->isValid()) {
                $result['has_file'] = true;
                $result['name'] = $fileOrUrl->getClientOriginalName();
                $result['mime'] = $fileOrUrl->getMimeType() ?: 'application/octet-stream';
                $bytes = @file_get_contents($fileOrUrl->getRealPath());
                if (! empty($bytes)) {
                    $result['data_uri'] = "data:{$result['mime']};base64,".base64_encode($bytes);
                    if (str_contains($result['mime'], 'pdf') || str_ends_with(strtolower($result['name']), '.pdf')) {
                        $result['text'] = $this->extractPdfText($bytes);
                    }
                }
            }

            return $result;
        }

        if (is_string($fileOrUrl)) {
            $trimmed = trim($fileOrUrl);
            $result['name'] = basename(parse_url($trimmed, PHP_URL_PATH) ?: '');

            $isHttp = str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://');
            if ($isHttp) {
                $result['has_file'] = true;
            }

            if (str_starts_with($trimmed, '/storage/') || str_starts_with($trimmed, 'storage/')) {
                $localPath = public_path(ltrim($trimmed, '/'));
                if (file_exists($localPath)) {
                    $bytes = file_get_contents($localPath);
                    if ($bytes) {
                        $result['mime'] = mime_content_type($localPath) ?: 'application/pdf';
                        $result['has_file'] = true;
                        $result['data_uri'] = "data:{$result['mime']};base64,".base64_encode($bytes);
                        if (str_contains($result['mime'], 'pdf') || str_ends_with(strtolower($result['name']), '.pdf')) {
                            $result['text'] = $this->extractPdfText($bytes);
                        }

                        return $result;
                    }
                }
            }

            $dataUri = $this->fetchDocumentAsDataUri($trimmed);
            if ($dataUri) {
                $result['has_file'] = true;
                $result['data_uri'] = $dataUri;
                if (preg_match('/^data:([^;]+);base64,(.*)$/', $dataUri, $m)) {
                    $result['mime'] = $m[1];
                    $bytes = base64_decode($m[2]);
                    if (str_contains($result['mime'], 'pdf')) {
                        $result['text'] = $this->extractPdfText($bytes);
                    }
                }
            }

            return $result;
        }

        return $result;
    }

    /**
     * Extract dealer name from Credit Note text or filename.
     */
    public function extractDealerNameFromCn(array $cnInfo): ?string
    {
        if (empty($cnInfo['has_file'])) {
            return null;
        }

        $text = $cnInfo['text'] ?? '';
        $filename = $cnInfo['name'] ?? '';

        // 1. Try regex on text
        if (! empty($text)) {
            if (preg_match('/(?:kepada\s*(?:yth)?|nama\s*(?:dealer|toko|pelanggan)|customer(?:\s*name)?|bill\s*to|ditujukan\s*kepada)\s*[:=\-]?\s*([A-Za-z0-9\s\.\,\&\-\'\"]{3,50})/i', $text, $m)) {
                $candidate = trim(preg_replace('/\s+/', ' ', $m[1]));
                if (! preg_match('/^(?:tanggal|no|nomor|alamat|telepon|telp|perihal|up|attention)/i', $candidate)) {
                    return $candidate;
                }
            }
        }

        // 2. Try regex on filename (e.g. "CN_NEWCO_CELL.pdf" or "Credit_Note_NEWCO_CELL.pdf")
        if (! empty($filename)) {
            // Ignore generic URL routes or endpoints
            if (in_array(strtolower($filename), ['open', 'view', 'edit', 'preview', 'uc', 'download', 'file', 'document', 'index', 'show'], true)) {
                return null;
            }

            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $clean = preg_replace('/^(?:CN|CREDIT[\s_\-]*NOTE|NOTA[\s_\-]*KREDIT|INVOICE|INV)[\s_\-]+/i', '', $nameWithoutExt);
            $clean = preg_replace('/[\s_\-]+(?:CN|CREDIT[\s_\-]*NOTE|NOTA[\s_\-]*KREDIT|INVOICE|INV)$/i', '', $clean);
            $clean = preg_replace('/^\d{4,14}[\s_\-]+/', '', $clean);
            $clean = trim(str_replace(['_', '-'], ' ', $clean));

            // Must have at least 4 characters, contain letters, and not be generic placeholders/URLs
            if (strlen($clean) >= 4
                && preg_match('/[a-zA-Z]{3,}/', $clean)
                && ! in_array(strtolower($clean), ['open', 'view', 'edit', 'preview', 'download', 'google', 'drive'], true)
                && ! preg_match('/^(?:document|file|scan|image|pdf|untitled|download|google|drive|sample|test|contoh|data)$/i', $clean)
                && ! preg_match('/^(?:cn|inv|invoice|nota|doc|file|scan|img|image|foto|berkas|preview|export|open|view)[\d\s_\-]*$/i', $clean)
            ) {
                return ucwords(strtolower($clean));
            }
        }

        return null;
    }

    /**
     * Compare user inputted dealer name with CN/Agreement dealer name with robust typo tolerance.
     */
    public function isDealerNameMatching(?string $inputName, ?string $cnName): bool
    {
        $input = trim((string) $inputName);
        $cn = trim((string) $cnName);

        if ($input === '' || $input === '-' || $cn === '' || $cn === '-') {
            return true;
        }

        $cleanInput = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $input));
        $cleanCn = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cn));

        if ($cleanInput === $cleanCn) {
            return true;
        }

        if (str_contains($cleanCn, $cleanInput) || str_contains($cleanInput, $cleanCn)) {
            return true;
        }

        // Collapse repeating consecutive characters (e.g. "newcoo" -> "newco", "newcoooo" -> "newco")
        $collapseRepeat = function (string $str): string {
            return preg_replace('/(.)\1+/', '$1', $str);
        };

        $collapsedInput = $collapseRepeat($cleanInput);
        $collapsedCn = $collapseRepeat($cleanCn);

        if ($collapsedInput === $collapsedCn || str_contains($collapsedCn, $collapsedInput) || str_contains($collapsedInput, $collapsedCn)) {
            return true;
        }

        $stripWords = function (string $str): string {
            return preg_replace('/\b(?:pt|cv|ud|toko|cell|cellular|selular|store|phone|telemarketing|cirebon)\b/i', '', strtolower($str));
        };

        $coreInput = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $stripWords($input)));
        $coreCn = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $stripWords($cn)));

        if ($coreInput !== '' && $coreCn !== '') {
            if ($coreInput === $coreCn || str_contains($coreCn, $coreInput) || str_contains($coreInput, $coreCn)) {
                return true;
            }

            $collapsedCoreInput = $collapseRepeat($coreInput);
            $collapsedCoreCn = $collapseRepeat($coreCn);
            if ($collapsedCoreInput === $collapsedCoreCn || str_contains($collapsedCoreCn, $collapsedCoreInput) || str_contains($collapsedCoreInput, $collapsedCoreCn)) {
                return true;
            }

            // Levenshtein distance on core words (allow 1-2 minor typos if sufficiently long)
            if (min(strlen($coreInput), strlen($coreCn)) >= 3) {
                if (levenshtein($coreInput, $coreCn) <= 2) {
                    return true;
                }
            }
        }

        // Levenshtein on full clean string (allow 1-2 minor typos if sufficiently long)
        if (min(strlen($cleanInput), strlen($cleanCn)) >= 4) {
            if (levenshtein($cleanInput, $cleanCn) <= 2) {
                return true;
            }
        }

        similar_text($cleanInput, $cleanCn, $percent);
        if ($percent >= 60) {
            return true;
        }

        return false;
    }

    /**
     * Extract dealer name from Agreement text or filename.
     */
    public function extractDealerNameFromAgr(array $agrInfo): ?string
    {
        if (empty($agrInfo['has_file'])) {
            return null;
        }

        $text = $agrInfo['text'] ?? '';
        $filename = $agrInfo['name'] ?? '';

        // 1. Try regex on text
        if (! empty($text)) {
            if (preg_match('/(?:pihak\s*(?:kedua|ii|2)|nama\s*(?:dealer|toko|mitra|outlet)|dealer|toko|outlet|bertindak\s*(?:untuk\s*dan\s*)?atas\s*nama|bill\s*to|kepada\s*(?:yth)?)\s*[:=\-]?\s*([A-Za-z0-9\s\.\,\&\-\'\"]{3,50})/i', $text, $m)) {
                $candidate = trim(preg_replace('/\s+/', ' ', $m[1]));
                if (! preg_match('/^(?:pt\s*realme|tanggal|no|nomor|alamat|telepon|telp|pasal|pada\s*hari)/i', $candidate)) {
                    return $candidate;
                }
            }
        }

        // 2. Try regex on filename (e.g. "AGR_NEWCO_CELL.pdf" or "Agreement_NEWCO_CELL.pdf")
        if (! empty($filename)) {
            if (in_array(strtolower($filename), ['open', 'view', 'edit', 'preview', 'uc', 'download', 'file', 'document', 'index', 'show'], true)) {
                return null;
            }

            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $clean = preg_replace('/^(?:AGR|AGREEMENT|PERJANJIAN|SURAT[\s_\-]*PERJANJIAN)[\s_\-]+/i', '', $nameWithoutExt);
            $clean = preg_replace('/[\s_\-]+(?:AGR|AGREEMENT|PERJANJIAN)$/i', '', $clean);
            $clean = preg_replace('/^\d{4,14}[\s_\-]+/', '', $clean);
            $clean = trim(str_replace(['_', '-'], ' ', $clean));

            if (strlen($clean) >= 4
                && preg_match('/[a-zA-Z]{3,}/', $clean)
                && ! in_array(strtolower($clean), ['open', 'view', 'edit', 'preview', 'download', 'google', 'drive'], true)
                && ! preg_match('/^(?:document|file|scan|image|pdf|untitled|download|google|drive|sample|test|contoh|data)$/i', $clean)
                && ! preg_match('/^(?:agr|agreement|perjanjian|doc|file|scan|img|image|foto|berkas|preview|export|open|view)[\d\s_\-]*$/i', $clean)
                && ! preg_match('/^program[\s_\-]/i', $clean)
            ) {
                return ucwords(strtolower($clean));
            }
        }

        return null;
    }

    /**
     * Extract program name from Agreement or CN document text/filename.
     */
    public function extractProgramNameFromDoc(array $docInfo): ?string
    {
        if (empty($docInfo['has_file'])) {
            return null;
        }

        $text = $docInfo['text'] ?? '';
        $filename = $docInfo['name'] ?? '';

        // 1. Try regex on text
        if (! empty($text)) {
            if (preg_match('/(?:program|nama\s*program|judul\s*program|perjanjian\s*program|pelaksanaan\s*program|kerjasama\s*program|perihal)\s*[:=\-]?\s*([A-Za-z0-9\s\.\,\&\-\'\(\)\/]{3,60})/i', $text, $m)) {
                $candidate = trim(preg_replace('/\s+/', ' ', $m[1]));
                if (! preg_match('/^(?:tanggal|no|nomor|pihak|pasal|pada\s*hari|realme)/i', $candidate)) {
                    return $candidate;
                }
            }
        }

        // 2. Try regex on filename (e.g. "AGR_PROGRAM_DSA_JULI_2026.pdf" or "PROGRAM_DSA_JULI_2026.pdf")
        if (! empty($filename)) {
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            if (preg_match('/(?:PROGRAM[_\-\s].*)/i', $nameWithoutExt, $m)) {
                $clean = preg_replace('/^(?:AGR|AGREEMENT|PERJANJIAN)[_\-\s]+/i', '', $m[0]);
                $clean = trim(str_replace(['_', '-'], ' ', $clean));
                if (strlen($clean) >= 4 && ! preg_match('/^(?:document|file|pdf|scan)$/i', $clean)) {
                    return ucwords(strtolower($clean));
                }
            }
        }

        return null;
    }

    /**
     * Compare user inputted program name with document program name.
     */
    public function isProgramNameMatching(?string $formProg, ?string $docProg): bool
    {
        $form = trim((string) $formProg);
        $doc = trim((string) $docProg);

        if ($form === '' || $doc === '') {
            return true;
        }

        $cleanForm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $form));
        $cleanDoc = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $doc));

        if ($cleanForm === $cleanDoc || str_contains($cleanForm, $cleanDoc) || str_contains($cleanDoc, $cleanForm)) {
            return true;
        }

        $stripWords = function (string $str): string {
            return preg_replace('/\b(?:program|kerjasama|perjanjian|cashback|so|sell\s*out|refund|periode|series)\b/i', '', strtolower($str));
        };

        $coreForm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $stripWords($form)));
        $coreDoc = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $stripWords($doc)));

        if ($coreForm !== '' && $coreDoc !== '') {
            if ($coreForm === $coreDoc || str_contains($coreForm, $coreDoc) || str_contains($coreDoc, $coreForm)) {
                return true;
            }
        }

        similar_text($cleanForm, $cleanDoc, $percent);
        if ($percent >= 60) {
            return true;
        }

        return false;
    }

    /**
     * Smart local heuristic document inspection when AI router is offline or for instant validation.
     */
    public function heuristicDocumentInspection(array $formData, array $filesOrUrls): array
    {
        $infos = [
            'cn' => $this->extractFileContentInfo($filesOrUrls['cn'] ?? null),
            'agr' => $this->extractFileContentInfo($filesOrUrls['agr'] ?? null),
            'faktur' => $this->extractFileContentInfo($filesOrUrls['faktur'] ?? null),
        ];

        $classifySlot = function (array $info): string {
            if (! $info['has_file']) {
                return 'none';
            }
            $haystack = strtoupper($info['name'].' '.$info['text']);

            $hasCnKw = (bool) preg_match('/credit[\s_\-]*note|nota[\s_\-]*kredit|potongan|incentive|\bcn[_\-\s0-9]|^cn\./i', $haystack);
            $hasAgrKw = (bool) preg_match('/agreement|perjanjian|kesepakatan|pihak[\s_\-]*pertama|\bagr[_\-\s0-9]|^agr\./i', $haystack);
            $hasFakturKw = (bool) preg_match('/faktur[\s_\-]*pajak|pengusaha[\s_\-]*kena[\s_\-]*pajak|010\.\d{3}/i', $haystack);

            if ($hasFakturKw && ! $hasCnKw) {
                return 'faktur';
            }
            if ($hasAgrKw && ! $hasCnKw) {
                return 'agr';
            }
            if ($hasCnKw && ! $hasAgrKw) {
                return 'cn';
            }
            if ($hasCnKw) {
                return 'cn';
            }
            if ($hasAgrKw) {
                return 'agr';
            }

            return 'unknown';
        };

        $detectedTypes = [
            'cn' => $classifySlot($infos['cn']),
            'agr' => $classifySlot($infos['agr']),
            'faktur' => $classifySlot($infos['faktur']),
        ];

        $docValidation = [];
        $hasSwapped = false;
        $hasInvalid = false;
        $swapDetails = [];

        // Validate CN slot
        if (! $infos['cn']['has_file']) {
            $docValidation['cn'] = ['status' => 'empty', 'actual_type' => 'none', 'message' => 'Dokumen Credit Note belum diunggah.'];
        } elseif ($detectedTypes['cn'] === 'agr') {
            $docValidation['cn'] = ['status' => 'swapped', 'actual_type' => 'agr', 'message' => 'File di slot Credit Note terdeteksi sebagai Dokumen Agreement (Tertukar)!'];
            $hasSwapped = true;
            $swapDetails[] = 'CN (AGR)';
        } elseif ($detectedTypes['cn'] === 'faktur') {
            $docValidation['cn'] = ['status' => 'swapped', 'actual_type' => 'faktur', 'message' => 'File di slot Credit Note terdeteksi sebagai Faktur Pajak (Tertukar)!'];
            $hasSwapped = true;
            $swapDetails[] = 'CN (FAKTUR)';
        } else {
            $docValidation['cn'] = ['status' => 'valid', 'actual_type' => 'cn', 'message' => 'Dokumen Credit Note terverifikasi.'];
        }

        // Validate AGR slot
        if (! $infos['agr']['has_file']) {
            $docValidation['agr'] = ['status' => 'empty', 'actual_type' => 'none', 'message' => 'Dokumen Agreement belum diunggah.'];
        } elseif ($detectedTypes['agr'] === 'cn') {
            $docValidation['agr'] = ['status' => 'swapped', 'actual_type' => 'cn', 'message' => 'File di slot Agreement terdeteksi sebagai Dokumen Credit Note (Tertukar)!'];
            $hasSwapped = true;
            $swapDetails[] = 'AGR (CN)';
        } elseif ($detectedTypes['agr'] === 'faktur') {
            $docValidation['agr'] = ['status' => 'swapped', 'actual_type' => 'faktur', 'message' => 'File di slot Agreement terdeteksi sebagai Faktur Pajak (Tertukar)!'];
            $hasSwapped = true;
            $swapDetails[] = 'AGR (FAKTUR)';
        } else {
            $docValidation['agr'] = ['status' => 'valid', 'actual_type' => 'agr', 'message' => 'Dokumen Agreement terverifikasi.'];
        }

        // Validate Faktur slot
        if (! $infos['faktur']['has_file']) {
            $docValidation['faktur'] = ['status' => 'empty', 'actual_type' => 'none', 'message' => 'Faktur Pajak tidak diunggah.'];
        } elseif ($detectedTypes['faktur'] === 'cn') {
            $docValidation['faktur'] = ['status' => 'swapped', 'actual_type' => 'cn', 'message' => 'File di slot Faktur terdeteksi sebagai Credit Note (Tertukar)!'];
            $hasSwapped = true;
            $swapDetails[] = 'FAKTUR (CN)';
        } elseif ($detectedTypes['faktur'] === 'agr') {
            $docValidation['faktur'] = ['status' => 'swapped', 'actual_type' => 'agr', 'message' => 'File di slot Faktur terdeteksi sebagai Agreement (Tertukar)!'];
            $hasSwapped = true;
            $swapDetails[] = 'FAKTUR (AGR)';
        } else {
            $docValidation['faktur'] = ['status' => 'valid', 'actual_type' => 'faktur', 'message' => 'Dokumen Faktur Pajak terverifikasi.'];
        }

        // Extract financial numbers if available in CN text
        $dpp = null;
        $ppn = 0.0;
        $nilaiPph = null;
        $netPay = null;
        $noFaktur = null;
        $tglFaktur = null;

        $cnText = $infos['cn']['text'];
        if (preg_match('/(?:dpp|dasar\s*pengenaan\s*pajak)\s*[:=]?\s*(?:rp\.?\s*)?([\d\.,]+)/i', $cnText, $m)) {
            $dpp = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }
        if (preg_match('/(?:ppn|pajak\s*pertambahan\s*nilai)\s*[:=]?\s*(?:rp\.?\s*)?([\d\.,]+)/i', $cnText, $m)) {
            $ppn = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }
        if (preg_match('/(?:pph|pajak\s*penghasilan)\s*[:=]?\s*(?:rp\.?\s*)?([\d\.,]+)/i', $cnText, $m)) {
            $nilaiPph = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }
        if (preg_match('/(?:net\s*pay|total\s*bayar|diterima)\s*[:=]?\s*(?:rp\.?\s*)?([\d\.,]+)/i', $cnText, $m)) {
            $netPay = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        $fakturText = $infos['faktur']['text'].' '.$cnText;
        if (preg_match('/(010\.\d{3}[-\.]\d{2}[-\.]\d{8})/', $fakturText, $m)) {
            $noFaktur = $m[1];
        }

        $isPkp = (! empty($formData['is_pkp'])) || ($ppn > 0) || (! empty($noFaktur));
        $missing = [];
        if (! $infos['cn']['has_file']) {
            $missing[] = 'CN';
        }
        if (! $infos['agr']['has_file']) {
            $missing[] = 'AGR';
        }
        if ($isPkp && ! $infos['faktur']['has_file']) {
            $missing[] = 'FAKTUR';
        }

        if ($hasSwapped) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = 'DOKUMEN TERTUKAR ('.implode(', ', $swapDetails).')';
            $keterangan = 'Dokumen tertukar posisi upload. Harap perbaiki posisi slot Credit Note dan Agreement.';
        } elseif (! empty($missing)) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = implode(' & ', $missing).' BELUM ADA';
            $keterangan = 'Dokumen belum lengkap ('.$cekDokumen.').';
        } else {
            $statusPurchase = 'BISA DI POTONG';
            $cekDokumen = 'LENGKAP';
            $keterangan = $isPkp
                ? 'Semua dokumen (CN, Agreement, Faktur Pajak) lengkap dan terverifikasi.'
                : 'Dokumen (CN dan Agreement) lengkap untuk dealer Non PKP dan siap diproses potong.';
        }

        $extractedDealerName = $this->extractDealerNameFromCn($infos['cn']);
        $dealerMismatch = false;
        $inputDealer = trim((string) ($formData['dealer_name'] ?? ''));

        if (! $hasSwapped && empty($missing) && $extractedDealerName && $inputDealer !== '' && $inputDealer !== '-') {
            if (! $this->isDealerNameMatching($inputDealer, $extractedDealerName)) {
                $dealerMismatch = true;
                $hasInvalid = true;
                $docValidation['cn'] = [
                    'status' => 'invalid',
                    'actual_type' => 'cn',
                    'message' => "Nama dealer di dokumen ('{$extractedDealerName}') berbeda dengan isian formulir ('{$inputDealer}').",
                ];
                $statusPurchase = 'BELUM BISA POTONG';
                $cekDokumen = 'NAMA DEALER TIDAK SESUAI (CN)';
                $keterangan = "Nama dealer di formulir ('{$inputDealer}') berbeda dengan nama dealer pada dokumen Credit Note ('{$extractedDealerName}'). Harap sesuaikan atau isi '-' agar otomatis diambil dari dokumen.";
            }
        }

        // Agreement Dealer & Program analysis
        $extractedAgrDealer = $this->extractDealerNameFromAgr($infos['agr']);
        $agrDealerMismatch = false;

        $extractedAgrProgram = $this->extractProgramNameFromDoc($infos['agr']);
        $agrProgramMismatch = false;

        $inputProgram = trim((string) ($formData['program_name'] ?? ''));

        // Check Agreement Dealer Mismatch
        if (! $hasSwapped && empty($missing) && $extractedAgrDealer) {
            $effectiveDealer = ($inputDealer !== '' && $inputDealer !== '-') ? $inputDealer : $extractedDealerName;
            if ($effectiveDealer && ! $this->isDealerNameMatching($effectiveDealer, $extractedAgrDealer)) {
                $agrDealerMismatch = true;
                $hasInvalid = true;
                $docValidation['agr'] = [
                    'status' => 'invalid',
                    'actual_type' => 'agr',
                    'message' => "Nama dealer pada Agreement ('{$extractedAgrDealer}') berbeda dengan nama dealer yang diajukan ('{$effectiveDealer}').",
                ];
                $statusPurchase = 'BELUM BISA POTONG';
                $cekDokumen = 'NAMA DEALER TIDAK SESUAI (AGR)';
                $keterangan = "Nama dealer di formulir ('{$effectiveDealer}') berbeda dengan nama dealer pada dokumen Agreement ('{$extractedAgrDealer}'). Harap sesuaikan dokumen Agreement Anda.";
            }
        }

        // Check Agreement Program Mismatch
        if (! $hasSwapped && empty($missing) && $extractedAgrProgram && $inputProgram !== '') {
            if (! $this->isProgramNameMatching($inputProgram, $extractedAgrProgram)) {
                $agrProgramMismatch = true;
                $hasInvalid = true;
                $docValidation['agr'] = [
                    'status' => 'invalid',
                    'actual_type' => 'agr',
                    'message' => "Nama program pada Agreement ('{$extractedAgrProgram}') berbeda dengan program yang dipilih ('{$inputProgram}').",
                ];
                $statusPurchase = 'BELUM BISA POTONG';
                $cekDokumen = 'NAMA PROGRAM TIDAK SESUAI (AGR)';
                $keterangan = "Nama program di formulir ('{$inputProgram}') berbeda dengan nama program pada dokumen Agreement ('{$extractedAgrProgram}'). Harap sesuaikan dokumen Agreement Anda.";
            }
        }

        return [
            'cek_dokumen' => $cekDokumen,
            'status_potong_purchase' => $statusPurchase,
            'keterangan' => $keterangan,
            'dealer_name' => $extractedDealerName ?: $extractedAgrDealer,
            'dealer_mismatch' => $dealerMismatch,
            'agr_dealer_name' => $extractedAgrDealer,
            'agr_dealer_mismatch' => $agrDealerMismatch,
            'agr_program_name' => $extractedAgrProgram,
            'agr_program_mismatch' => $agrProgramMismatch,
            'is_complete' => ($statusPurchase === 'BISA DI POTONG'),
            'dpp' => $dpp,
            'dpp_lain' => 0.0,
            'ppn' => $ppn,
            'nilai_pph' => $nilaiPph,
            'net_pay' => $netPay,
            'incentive' => $dpp !== null ? ($isPkp ? round($dpp + $ppn) : round($dpp * 1.11, -3)) : null,
            'no_faktur' => $noFaktur,
            'tgl_faktur' => $tglFaktur,
            'has_stamp' => true,
            'has_signature' => true,
            'has_npwp' => true,
            'note_pph' => 'ok',
            'doc_validation' => $docValidation,
        ];
    }

    /**
     * Comprehensive inspection of document files or URLs with AI vision & smart fallback.
     *
     * @param  array  $formData  Form values (dealer_name, program_name, id_real, region, sales_name, whatsapp, is_pkp)
     * @param  array  $filesOrUrls  Slot mapping ('cn' => ..., 'agr' => ..., 'faktur' => ...)
     * @return array{
     *     success: bool,
     *     is_clean: bool,
     *     has_swapped: bool,
     *     has_invalid: bool,
     *     swap_details: array,
     *     cek_dokumen: string,
     *     status_potong_purchase: string,
     *     keterangan: string,
     *     doc_validation: array,
     *     financial: array,
     *     audit: array,
     *     raw_analysis: array
     * }
     */
    public function inspectDocumentFiles(array $formData, array $filesOrUrls): array
    {
        $config = $this->getConfig();
        $cnDataUri = $this->fileOrUrlToDataUri($filesOrUrls['cn'] ?? null);
        $agrDataUri = $this->fileOrUrlToDataUri($filesOrUrls['agr'] ?? null);
        $taxDataUri = $this->fileOrUrlToDataUri($filesOrUrls['faktur'] ?? null);

        $hasDoc = function ($val): bool {
            if (empty($val)) {
                return false;
            }
            if ($val instanceof UploadedFile) {
                return $val->isValid();
            }
            if (is_string($val)) {
                $trimmed = trim($val);

                return $trimmed !== '' && $trimmed !== '-' && (
                    str_starts_with($trimmed, 'http://') ||
                    str_starts_with($trimmed, 'https://') ||
                    str_starts_with($trimmed, '/storage/') ||
                    str_starts_with($trimmed, 'storage/') ||
                    str_starts_with($trimmed, 'data:')
                );
            }

            return false;
        };

        $slotHasUrl = [
            'cn' => $hasDoc($filesOrUrls['cn'] ?? null),
            'agr' => $hasDoc($filesOrUrls['agr'] ?? null),
            'faktur' => $hasDoc($filesOrUrls['faktur'] ?? null),
        ];

        $parsed = null;

        // Try AI Vision Router if API key is configured
        if (! empty($config['api_key'])) {
            try {
                $userParts = [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'id_real' => $formData['id_real'] ?? '-',
                            'dealer_name' => $formData['dealer_name'] ?? '-',
                            'program_name' => $formData['program_name'] ?? '-',
                            'sales_name' => $formData['sales_name'] ?? '-',
                            'status_pajak' => ! empty($formData['is_pkp']) ? 'PKP' : 'NON PKP',
                            'has_cn_file' => $slotHasUrl['cn'],
                            'has_agr_file' => $slotHasUrl['agr'],
                            'has_faktur_file' => $slotHasUrl['faktur'],
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ],
                ];

                if ($cnDataUri) {
                    $userParts[] = ['type' => 'text', 'text' => '--- LAMPIRAN DOKUMEN CREDIT NOTE (CN) ---'];
                    $userParts[] = ['type' => 'image_url', 'image_url' => ['url' => $cnDataUri]];
                }
                if ($taxDataUri) {
                    $userParts[] = ['type' => 'text', 'text' => '--- LAMPIRAN DOKUMEN FAKTUR PAJAK ---'];
                    $userParts[] = ['type' => 'image_url', 'image_url' => ['url' => $taxDataUri]];
                }
                if ($agrDataUri) {
                    $userParts[] = ['type' => 'text', 'text' => '--- LAMPIRAN DOKUMEN AGREEMENT (AGR) ---'];
                    $userParts[] = ['type' => 'image_url', 'image_url' => ['url' => $agrDataUri]];
                }

                $payload = [
                    'model' => $config['model'] ?: 'ag/gemini-3.7-flash-low',
                    'messages' => [
                        ['role' => 'system', 'content' => $this->getSystemPrompt()],
                        ['role' => 'user', 'content' => $userParts],
                    ],
                    'stream' => false,
                    'temperature' => 0.1,
                ];

                $response = Http::withoutVerifying()
                    ->withToken($config['api_key'])
                    ->timeout(30)
                    ->post("{$config['base_url']}/chat/completions", $payload);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');
                    if (! empty($content)) {
                        $parsed = $this->parseJsonResponse($content);
                    }
                }
            } catch (\Throwable $e) {
                Log::info('AI vision call failed, using heuristic fallback: '.$e->getMessage());
            }
        }

        // Fallback to intelligent local heuristics if AI call didn't yield valid parsed result
        if (! is_array($parsed)) {
            $parsed = $this->heuristicDocumentInspection($formData, $filesOrUrls);
        }

        // Process financial numbers
        $dpp = isset($parsed['dpp']) && is_numeric($parsed['dpp']) ? (float) $parsed['dpp'] : ($formData['dpp'] ?? null);
        $dppLain = isset($parsed['dpp_lain']) && is_numeric($parsed['dpp_lain']) ? (float) $parsed['dpp_lain'] : ($formData['dpp_lain'] ?? 0.0);
        $ppn = isset($parsed['ppn']) && is_numeric($parsed['ppn']) ? (float) $parsed['ppn'] : ($formData['ppn'] ?? 0.0);
        $nilaiPph = isset($parsed['nilai_pph']) && is_numeric($parsed['nilai_pph']) ? (float) $parsed['nilai_pph'] : ($formData['nilai_pph'] ?? null);
        $netPay = isset($parsed['net_pay']) && is_numeric($parsed['net_pay']) ? (float) $parsed['net_pay'] : ($formData['net_pay'] ?? null);

        $hasPpn = ! empty($ppn) && (float) $ppn > 0;
        $hasFaktur = (! empty($parsed['no_faktur']) && trim((string) $parsed['no_faktur']) !== '-') || $slotHasUrl['faktur'];
        $isPkp = $hasPpn || $hasFaktur || (! empty($formData['is_pkp']));

        $incentive = isset($parsed['incentive']) && is_numeric($parsed['incentive']) ? (float) $parsed['incentive'] : ($formData['incentive'] ?? null);
        if ($dpp !== null && ($incentive === null || abs($incentive - $dpp) < 0.01)) {
            if ($hasPpn) {
                $incentive = (float) round($dpp + $ppn);
            } else {
                $gross = round($dpp * 1.11);
                $incentive = (abs($gross - round($gross, -3)) <= 15) ? (float) round($gross, -3) : (float) $gross;
            }
        } elseif ($incentive === null && $dpp !== null) {
            $incentive = $dpp;
        }

        if ($netPay === null && $dpp !== null) {
            $netPay = round($dpp + ($ppn ?? 0) - ($nilaiPph ?? 0), 2);
        }

        $noFaktur = ! empty($parsed['no_faktur']) ? trim((string) $parsed['no_faktur']) : ($formData['no_faktur'] ?? null);
        $tglFaktur = ! empty($parsed['tgl_faktur']) ? trim((string) $parsed['tgl_faktur']) : ($formData['tgl_faktur'] ?? null);

        // Process doc_validation slot mapping
        $docValidation = [];
        $rawDocVal = $parsed['doc_validation'] ?? [];
        foreach (['cn', 'agr', 'faktur'] as $slot) {
            if (! $slotHasUrl[$slot]) {
                $docValidation[$slot] = [
                    'status' => 'empty',
                    'actual_type' => 'none',
                    'message' => ($slot === 'faktur' && ! $isPkp) ? 'Faktur Pajak tidak wajib untuk Non-PKP' : 'Dokumen belum diunggah',
                ];

                continue;
            }

            if (isset($rawDocVal[$slot]) && is_array($rawDocVal[$slot])) {
                $status = in_array($rawDocVal[$slot]['status'] ?? '', ['valid', 'swapped', 'invalid', 'empty'], true)
                    ? $rawDocVal[$slot]['status']
                    : 'valid';
                $docValidation[$slot] = [
                    'status' => $status,
                    'actual_type' => (string) ($rawDocVal[$slot]['actual_type'] ?? $slot),
                    'message' => (string) ($rawDocVal[$slot]['message'] ?? 'Dokumen diunggah'),
                ];
            } else {
                $docValidation[$slot] = [
                    'status' => 'valid',
                    'actual_type' => $slot,
                    'message' => 'Dokumen diunggah',
                ];
            }
        }

        $hasSwapped = false;
        $hasInvalid = false;
        $swapDetails = [];
        $invalidDetails = [];

        foreach ($docValidation as $slot => $info) {
            if (! empty($slotHasUrl[$slot])) {
                if (($info['status'] ?? '') === 'swapped') {
                    $hasSwapped = true;
                    $swapDetails[$slot] = strtoupper($info['actual_type'] ?? '');
                } elseif (($info['status'] ?? '') === 'invalid') {
                    $hasInvalid = true;
                    $invalidDetails[] = strtoupper($slot);
                }
            }
        }

        $missingDocs = [];
        if (! $slotHasUrl['cn']) {
            $missingDocs[] = 'CN';
        }
        if (! $slotHasUrl['agr']) {
            $missingDocs[] = 'AGR';
        }
        if ($isPkp && ! $slotHasUrl['faktur']) {
            $missingDocs[] = 'FAKTUR';
        }

        if ($hasSwapped) {
            $statusPurchase = 'BELUM BISA POTONG';
            $swapLabels = [];
            foreach ($swapDetails as $k => $v) {
                $swapLabels[] = strtoupper($k).' ('.$v.')';
            }
            $cekDokumen = 'DOKUMEN TERTUKAR ('.implode(', ', $swapLabels).')';
            $keterangan = 'Dokumen tertukar antar kolom. Harap perbaiki posisi upload dokumen.';
        } elseif ($hasInvalid) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = 'DOKUMEN TIDAK SESUAI ('.implode(', ', $invalidDetails).')';
            $keterangan = 'File dokumen yang diunggah tidak sesuai atau tidak terbaca.';
        } elseif (! empty($missingDocs)) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = implode(' & ', $missingDocs).' BELUM ADA';
            $keterangan = 'Dokumen belum lengkap ('.$cekDokumen.').';
        } else {
            $statusPurchase = 'BISA DI POTONG';
            $cekDokumen = 'LENGKAP';
            $keterangan = $isPkp
                ? 'Semua dokumen (CN, Agreement, Faktur Pajak) lengkap dan terverifikasi.'
                : 'Dokumen (CN dan Agreement) lengkap untuk dealer Non PKP dan siap diproses potong.';
        }

        $extractedDealerName = null;
        if (! empty($parsed['dealer_name']) && trim((string) $parsed['dealer_name']) !== '-' && trim((string) $parsed['dealer_name']) !== '') {
            $extractedDealerName = trim((string) $parsed['dealer_name']);
        } else {
            $extractedDealerName = $this->extractDealerNameFromCn($this->extractFileContentInfo($filesOrUrls['cn'] ?? null));
        }

        $dealerMismatch = false;
        $inputDealer = trim((string) ($formData['dealer_name'] ?? ''));

        if (! $hasSwapped && empty($missingDocs) && $extractedDealerName && $inputDealer !== '' && $inputDealer !== '-') {
            if ($this->isDealerNameMatching($inputDealer, $extractedDealerName)) {
                $dealerMismatch = false;
                if (isset($docValidation['cn']) && $docValidation['cn']['status'] === 'invalid') {
                    $cnMsg = strtolower($docValidation['cn']['message'] ?? '');
                    if (str_contains($cnMsg, 'dealer') || str_contains($cnMsg, 'berbeda') || str_contains($cnMsg, 'nama')) {
                        $docValidation['cn']['status'] = 'valid';
                        $docValidation['cn']['message'] = 'Dokumen Credit Note terverifikasi.';
                    }
                }
            } else {
                $dealerMismatch = true;
                $docValidation['cn'] = [
                    'status' => 'invalid',
                    'actual_type' => 'cn',
                    'message' => "Nama dealer di dokumen ('{$extractedDealerName}') berbeda dengan isian formulir ('{$inputDealer}').",
                ];
            }
        } elseif (! empty($parsed['dealer_mismatch']) && $extractedDealerName && $inputDealer !== '' && $inputDealer !== '-') {
            // Re-verify parsed dealer mismatch with typo tolerance
            if ($this->isDealerNameMatching($inputDealer, $extractedDealerName)) {
                $dealerMismatch = false;
                if (isset($docValidation['cn']) && $docValidation['cn']['status'] === 'invalid') {
                    $cnMsg = strtolower($docValidation['cn']['message'] ?? '');
                    if (str_contains($cnMsg, 'dealer') || str_contains($cnMsg, 'berbeda') || str_contains($cnMsg, 'nama')) {
                        $docValidation['cn']['status'] = 'valid';
                        $docValidation['cn']['message'] = 'Dokumen Credit Note terverifikasi.';
                    }
                }
            } else {
                $dealerMismatch = true;
            }
        }

        // Agreement Dealer & Program analysis
        $extractedAgrDealer = null;
        if (! empty($parsed['agr_dealer_name']) && trim((string) $parsed['agr_dealer_name']) !== '-' && trim((string) $parsed['agr_dealer_name']) !== '') {
            $extractedAgrDealer = trim((string) $parsed['agr_dealer_name']);
        } else {
            $extractedAgrDealer = $this->extractDealerNameFromAgr($this->extractFileContentInfo($filesOrUrls['agr'] ?? null));
        }

        $extractedAgrProgram = null;
        if (! empty($parsed['agr_program_name']) && trim((string) $parsed['agr_program_name']) !== '-' && trim((string) $parsed['agr_program_name']) !== '') {
            $extractedAgrProgram = trim((string) $parsed['agr_program_name']);
        } else {
            $extractedAgrProgram = $this->extractProgramNameFromDoc($this->extractFileContentInfo($filesOrUrls['agr'] ?? null));
        }

        $agrDealerMismatch = false;
        $agrProgramMismatch = false;

        $inputProgram = trim((string) ($formData['program_name'] ?? ''));

        // Check Agreement Dealer Mismatch
        if (! $hasSwapped && empty($missingDocs) && $extractedAgrDealer) {
            $effectiveDealer = ($inputDealer !== '' && $inputDealer !== '-') ? $inputDealer : $extractedDealerName;
            if ($effectiveDealer && $this->isDealerNameMatching($effectiveDealer, $extractedAgrDealer)) {
                $agrDealerMismatch = false;
                if (isset($docValidation['agr']) && $docValidation['agr']['status'] === 'invalid') {
                    $agrMsg = strtolower($docValidation['agr']['message'] ?? '');
                    if (str_contains($agrMsg, 'dealer') || str_contains($agrMsg, 'berbeda') || str_contains($agrMsg, 'nama')) {
                        $docValidation['agr']['status'] = 'valid';
                        $docValidation['agr']['message'] = 'Dokumen Agreement terverifikasi.';
                    }
                }
            } elseif ($effectiveDealer && ! $this->isDealerNameMatching($effectiveDealer, $extractedAgrDealer)) {
                $agrDealerMismatch = true;
                $docValidation['agr'] = [
                    'status' => 'invalid',
                    'actual_type' => 'agr',
                    'message' => "Nama dealer pada Agreement ('{$extractedAgrDealer}') berbeda dengan nama dealer yang diajukan ('{$effectiveDealer}').",
                ];
            }
        } elseif (! empty($parsed['agr_dealer_mismatch']) && $extractedAgrDealer) {
            $effectiveDealer = ($inputDealer !== '' && $inputDealer !== '-') ? $inputDealer : $extractedDealerName;
            if ($effectiveDealer && $this->isDealerNameMatching($effectiveDealer, $extractedAgrDealer)) {
                $agrDealerMismatch = false;
                if (isset($docValidation['agr']) && $docValidation['agr']['status'] === 'invalid') {
                    $agrMsg = strtolower($docValidation['agr']['message'] ?? '');
                    if (str_contains($agrMsg, 'dealer') || str_contains($agrMsg, 'berbeda') || str_contains($agrMsg, 'nama')) {
                        $docValidation['agr']['status'] = 'valid';
                        $docValidation['agr']['message'] = 'Dokumen Agreement terverifikasi.';
                    }
                }
            } else {
                $agrDealerMismatch = true;
            }
        }

        // Check Agreement Program Mismatch
        if (! $hasSwapped && empty($missingDocs) && $extractedAgrProgram && $inputProgram !== '') {
            if ($this->isProgramNameMatching($inputProgram, $extractedAgrProgram)) {
                $agrProgramMismatch = false;
                if (isset($docValidation['agr']) && $docValidation['agr']['status'] === 'invalid') {
                    $agrMsg = strtolower($docValidation['agr']['message'] ?? '');
                    if (str_contains($agrMsg, 'program') || str_contains($agrMsg, 'berbeda')) {
                        $docValidation['agr']['status'] = 'valid';
                        $docValidation['agr']['message'] = 'Dokumen Agreement terverifikasi.';
                    }
                }
            } else {
                $agrProgramMismatch = true;
                $docValidation['agr'] = [
                    'status' => 'invalid',
                    'actual_type' => 'agr',
                    'message' => "Nama program pada Agreement ('{$extractedAgrProgram}') berbeda dengan program yang dipilih ('{$inputProgram}').",
                ];
            }
        } elseif (! empty($parsed['agr_program_mismatch']) && $extractedAgrProgram && $inputProgram !== '') {
            if ($this->isProgramNameMatching($inputProgram, $extractedAgrProgram)) {
                $agrProgramMismatch = false;
                if (isset($docValidation['agr']) && $docValidation['agr']['status'] === 'invalid') {
                    $agrMsg = strtolower($docValidation['agr']['message'] ?? '');
                    if (str_contains($agrMsg, 'program') || str_contains($agrMsg, 'berbeda')) {
                        $docValidation['agr']['status'] = 'valid';
                        $docValidation['agr']['message'] = 'Dokumen Agreement terverifikasi.';
                    }
                }
            } else {
                $agrProgramMismatch = true;
            }
        }

        // Recompute invalid details from docValidation
        $hasInvalid = false;
        $invalidDetails = [];
        foreach ($docValidation as $slot => $info) {
            if (! empty($slotHasUrl[$slot]) && ($info['status'] ?? '') === 'invalid') {
                $hasInvalid = true;
                $invalidDetails[] = strtoupper($slot);
            }
        }

        // Finalize statusPurchase, cekDokumen, and keterangan
        if ($hasSwapped) {
            $statusPurchase = 'BELUM BISA POTONG';
            $swapLabels = [];
            foreach ($swapDetails as $k => $v) {
                $swapLabels[] = strtoupper($k).' ('.$v.')';
            }
            $cekDokumen = 'DOKUMEN TERTUKAR ('.implode(', ', $swapLabels).')';
            $keterangan = 'Dokumen tertukar antar kolom. Harap perbaiki posisi upload dokumen.';
        } elseif ($dealerMismatch) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = 'NAMA DEALER TIDAK SESUAI (CN)';
            $keterangan = "Nama dealer di formulir ('{$inputDealer}') berbeda dengan nama dealer pada dokumen Credit Note ('{$extractedDealerName}'). Harap sesuaikan atau isi '-' agar otomatis diambil dari dokumen.";
        } elseif ($agrDealerMismatch) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = 'NAMA DEALER TIDAK SESUAI (AGR)';
            $keterangan = "Nama dealer di formulir ('{$effectiveDealer}') berbeda dengan nama dealer pada dokumen Agreement ('{$extractedAgrDealer}'). Harap sesuaikan dokumen Agreement Anda.";
        } elseif ($agrProgramMismatch) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = 'NAMA PROGRAM TIDAK SESUAI (AGR)';
            $keterangan = "Nama program di formulir ('{$inputProgram}') berbeda dengan nama program pada dokumen Agreement ('{$extractedAgrProgram}'). Harap sesuaikan dokumen Agreement Anda.";
        } elseif ($hasInvalid) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = 'DOKUMEN TIDAK SESUAI ('.implode(', ', $invalidDetails).')';
            $keterangan = 'File dokumen yang diunggah tidak sesuai atau tidak terbaca.';
        } elseif (! empty($missingDocs)) {
            $statusPurchase = 'BELUM BISA POTONG';
            $cekDokumen = implode(' & ', $missingDocs).' BELUM ADA';
            $keterangan = 'Dokumen belum lengkap ('.$cekDokumen.').';
        } else {
            $statusPurchase = 'BISA DI POTONG';
            $cekDokumen = 'LENGKAP';
            $keterangan = $isPkp
                ? 'Semua dokumen (CN, Agreement, Faktur Pajak) lengkap dan terverifikasi.'
                : 'Dokumen (CN dan Agreement) lengkap untuk dealer Non PKP dan siap diproses potong.';
        }

        $isClean = (! $hasSwapped && ! $hasInvalid && empty($missingDocs) && ! $dealerMismatch && ! $agrDealerMismatch && ! $agrProgramMismatch);

        return [
            'success' => true,
            'is_clean' => $isClean,
            'has_swapped' => $hasSwapped,
            'has_invalid' => $hasInvalid,
            'dealer_name' => $extractedDealerName ?: $extractedAgrDealer,
            'dealer_mismatch' => $dealerMismatch,
            'agr_dealer_name' => $extractedAgrDealer,
            'agr_dealer_mismatch' => $agrDealerMismatch,
            'agr_program_name' => $extractedAgrProgram,
            'agr_program_mismatch' => $agrProgramMismatch,
            'swap_details' => $swapDetails,
            'cek_dokumen' => $cekDokumen,
            'status_potong_purchase' => $statusPurchase,
            'keterangan' => $keterangan,
            'doc_validation' => $docValidation,
            'financial' => [
                'incentive' => $incentive,
                'dpp' => $dpp,
                'dpp_lain' => $dppLain,
                'ppn' => $ppn,
                'nilai_pph' => $nilaiPph,
                'net_pay' => $netPay,
                'no_faktur' => $noFaktur,
                'tgl_faktur' => $tglFaktur,
            ],
            'audit' => [
                'has_stamp' => $parsed['has_stamp'] ?? true,
                'has_signature' => $parsed['has_signature'] ?? true,
                'has_npwp' => $parsed['has_npwp'] ?? true,
                'note_pph' => $parsed['note_pph'] ?? 'ok',
            ],
            'raw_analysis' => $parsed,
        ];
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
        $isPkp = ((float) ($submission->ppn ?? 0) > 0) || app(ProgramReconciliationService::class)->isPkpFromSubmission($submission);

        $res = $this->inspectDocumentFiles([
            'id_real' => $submission->id_real,
            'dealer_name' => $submission->dealer_name,
            'program_name' => $submission->program_name,
            'sales_name' => $submission->sales_name,
            'is_pkp' => $isPkp,
            'dpp' => $submission->dpp,
            'ppn' => $submission->ppn,
            'dpp_lain' => $submission->dpp_lain,
            'nilai_pph' => $submission->nilai_pph,
            'net_pay' => $submission->net_pay,
            'incentive' => $submission->incentive,
            'no_faktur' => $submission->no_faktur,
            'tgl_faktur' => $submission->tgl_faktur,
        ], [
            'cn' => $submission->credit_note_url,
            'agr' => $submission->agreement_url,
            'faktur' => $submission->tax_invoice_url,
        ]);

        $financialData = $res['financial'];
        $financialData['cek_pajak_tarif_pph'] = ($submission->is_manual_edit && $submission->cek_pajak_tarif_pph !== null)
            ? (float) $submission->cek_pajak_tarif_pph
            : 0.0;
        $financialData['selisih'] = ($submission->is_manual_edit && $submission->selisih !== null)
            ? (float) $submission->selisih
            : 0.0;
        $financialData['note_pph'] = $res['audit']['note_pph'] ?? 'ok';
        $financialData['doc_validation'] = $res['doc_validation'];

        if (($submission->dealer_name === '-' || empty($submission->dealer_name)) && ! empty($res['dealer_name'])) {
            $financialData['dealer_name'] = $res['dealer_name'];
        }

        $updatePayload = array_merge([
            'cek_dokumen' => $res['cek_dokumen'],
            'status_potong_purchase' => $res['status_potong_purchase'],
        ], $financialData);

        $submission->update($updatePayload);

        if (($submission->raw_data['source'] ?? '') !== 'web_form') {
            try {
                app(ProgramReconciliationService::class)->reconcileFromSubmission($submission->fresh());
            } catch (\Throwable $e) {
                Log::warning('Auto-reconciliation after AI analysis failed: '.$e->getMessage());
            }
        }

        return [
            'submission' => $submission->fresh(),
            'cek_dokumen' => $res['cek_dokumen'],
            'status_potong_purchase' => $res['status_potong_purchase'],
            'keterangan' => $submission->keterangan,
            'ai_keterangan' => $res['keterangan'],
            'financial' => $financialData,
            'raw_analysis' => $res['raw_analysis'],
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
  * cek_pajak_tarif_pph: Selalu isi 0 (pengecekan/penentuan tarif pajak dilakukan oleh Tim Pajak internal, bukan oleh AI)
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
1. Ketentuan Khusus Pajak (PKP vs NON PKP):
   - JIKA STATUS PAJAK ADALAH "PKP" (Badan atau Pribadi PKP, atau ada PPN):
     * Wajib melampirkan ketiga dokumen: CN, Agreement (AGR), dan Faktur Pajak.
     * Jika Faktur Pajak tidak ada: "is_complete": false, "status_potong_purchase": "BELUM BISA POTONG", "cek_dokumen": "FAKTUR BELUM ADA".
     * Jika ketiga dokumen lengkap & valid: "is_complete": true, "status_potong_purchase": "BISA DI POTONG", "cek_dokumen": "LENGKAP".

   - JIKA STATUS PAJAK ADALAH "NON PKP" (Orang Pribadi, Non PKP, PPN = 0):
     * TIDAK WAJIB FAKTUR PAJAK. Dokumen yang wajib hanya 2: CN dan Agreement (AGR).
     * Meskipun CUMA DUA dokumen (CN dan AGR) yang diunggah dan valid, status TETAP "BISA DI POTONG", "is_complete": true, "cek_dokumen": "LENGKAP". Faktur Pajak TIDAK DIPERLUKAN untuk dealer Non-PKP dan JANGAN PERNAH dicatat sebagai kekurangan / "FAKTUR BELUM ADA".
     * Hanya jika CN atau AGR belum ada / salah: "is_complete": false, "status_potong_purchase": "BELUM BISA POTONG", "cek_dokumen" mencatat dokumen yang kurang (misal "AGR BELUM ADA", tidak perlu menyebut Faktur).

2. PENTING - JIKA ADA DOKUMEN YANG BELUM DIUNGGAH / KOSONG (URL kosong atau bernilai "-"):
   - Slot tersebut diberi status "empty" (BUKAN "invalid"!).
   - Untuk dealer NON-PKP: Jika slot Faktur Pajak kosong, hal ini NORMAL dan DIBOLEHKAN. Jangan anggap dokumen kurang! Jika CN & AGR ada dan valid -> "status_potong_purchase": "BISA DI POTONG", "cek_dokumen": "LENGKAP".
   - Untuk dealer PKP (atau jika CN/AGR kosong pada Non-PKP):
     * "is_complete": false
     * "status_potong_purchase": "BELUM BISA POTONG"
     * "cek_dokumen": Wajib menyebutkan dokumen wajib yang belum ada (contoh: "FAKTUR BELUM ADA", "AGR BELUM ADA", "CN BELUM ADA", dsb).
     * "keterangan": Penjelasan ringkas dokumen wajib mana yang belum diunggah.

3. DOKUMEN TERTUKAR (swapped):
   (HANYA berlaku jika ADA file yang diunggah tetapi tertukar posisi antar slot):
   - "is_complete": false
   - "cek_dokumen": Sebutkan dokumen yang tertukar, contoh: "DOKUMEN TERTUKAR (CN ↔ AGR)"
   - "status_potong_purchase": "BELUM BISA POTONG"
   - "keterangan": "File dokumen tertukar antar kolom. Harap perbaiki posisi upload dokumen."

4. DOKUMEN TIDAK SESUAI (invalid):
   (HANYA berlaku jika ADA file yang diunggah pada slot tersebut tetapi isinya salah upload / bukan dokumen resmi yang diminta, ATAU isi dokumen tidak sesuai data form):
   * PENTING: JANGAN PERNAH mengubah data asli Form Program (nama dealer atau nama program TIDAK BOLEH diubah otomatis). Tugas AI murni mendeteksi dan memberikan CATATAN INFORMASI agar customer tahu letak perbedaannya dan dapat mengunggah ulang dokumen yang sesuai.
   - Jika NAMA PROGRAM di lembar CN BERBEDA dengan nama program di Form Program (misal customer salah input program):
     * Data form tetap dipertahankan (jangan diubah).
     * Tandai slot CN sebagai "invalid" agar status tertahan dan customer tahu perlu upload ulang dokumen yang cocok.
     * "message" pada slot CN: "Catatan: Nama program di form ('[Nama Program di Form]') berbeda dengan lembar CN ('[Nama Program di Lembar CN]'). Harap upload ulang dokumen yang sesuai."
     * "cek_dokumen": "DOKUMEN TIDAK SESUAI (CN)"
     * "status_potong_purchase": "BELUM BISA POTONG"
     * "keterangan": "Catatan: Nama program di form ('[Nama Program di Form]') berbeda dengan lembar CN ('[Nama Program di Lembar CN]'). Harap upload ulang dokumen yang sesuai."
   - Validasi Nama Dealer:
     * Selalu ekstrak nama dealer yang tertera pada lembar Credit Note ke field "dealer_name". Contoh: "NEWCO CELL", "CV TOP SELULAR", dsb.
     * Pengecualian: Jika nama dealer di form diisi "-", ini berarti customer meminta sistem membaca nama dealer otomatis dari dokumen CN. Dalam kondisi ini, JANGAN tandai sebagai invalid, dan isi field "dealer_name" dengan nama dealer yang tertera di dokumen CN.
     * TOLERANSI TYPO / BEDA DIKIT (SANGAT PENTING):
       - Jika nama dealer di formulir dan dokumen intinya SAMA meski ada sedikit perbedaan ketik / typo kecil (contoh: "NEWCOO CELL" vs "NEWCO CELL" yang cuma kurang/lebih huruf O, "NEWCO CELLULAR" vs "NEWCO CELL", selisih 1-2 huruf, spasi, atau singkatan PT/CV/CELL), MAKA WAJIB DIANGGAP SAMA & VALID! JANGAN anggap mismatch dan JANGAN tandai slot CN sebagai invalid!
       - Set "dealer_mismatch": false, status slot CN tetap "valid".
     * HANYA jika Form Program diisi nama dealer yang BENAR-BENAR BERBEDA dan toko lain sama sekali (misal formulir diisi "ocean" / "BINTANG" tetapi pada dokumen CN tertera "NEWCO CELL"):
       - Set "dealer_mismatch": true
       - Tandai slot CN sebagai "invalid"
       - "status_potong_purchase": "BELUM BISA POTONG"
       - "cek_dokumen": "NAMA DEALER TIDAK SESUAI (CN)"
       - "keterangan": "Nama dealer di formulir ('[Nama Dealer Form]') berbeda dengan dokumen Credit Note ('[Nama Dealer Dokumen]'). Harap perbaiki atau isi '-' agar nama otomatis diambil dari dokumen."
       - Pada doc_validation.cn.message: "Nama dealer di dokumen ('[Nama Dealer Dokumen]') berbeda dengan Form ('[Nama Dealer Form]')."
   - Validasi Dokumen Agreement (AGR) - NAMA DEALER & NAMA PROGRAM:
     * Ekstrak nama dealer pada lembar Agreement ke field "agr_dealer_name".
     * Ekstrak nama program pada lembar Agreement ke field "agr_program_name".
     * Jika nama dealer pada form diisi "-", nama dealer dapat diambil dari Agreement jika CN tidak memuatnya.
     * TOLERANSI TYPO: Jika nama dealer pada Agreement intinya sama dengan formulir/CN walau beda tipis / typo kecil (seperti "NEWCOO" vs "NEWCO"), WAJIB DIANGGAP SAMA ("agr_dealer_mismatch": false, status slot agr tetap "valid").
     * HANYA jika Nama Dealer pada Agreement BENAR-BENAR BERBEDA (toko lain sama sekali):
       - Set "agr_dealer_mismatch": true
       - Tandai slot agr sebagai "invalid"
       - "status_potong_purchase": "BELUM BISA POTONG"
       - "cek_dokumen": "NAMA DEALER TIDAK SESUAI (AGR)"
       - "keterangan": "Nama dealer di formulir ('[Nama Dealer Form]') berbeda dengan dokumen Agreement ('[Nama Dealer Agr]'). Harap sesuaikan dokumen Agreement."
       - Pada doc_validation.agr.message: "Nama dealer pada dokumen Agreement ('[Nama Dealer Agr]') berbeda dengan formulir ('[Nama Dealer Form]')."
     * Jika Nama Program pada Agreement BERBEDA dengan nama program yang diajukan di Form:
       - Set "agr_program_mismatch": true
       - Tandai slot agr sebagai "invalid"
       - "status_potong_purchase": "BELUM BISA POTONG"
       - "cek_dokumen": "NAMA PROGRAM TIDAK SESUAI (AGR)"
       - "keterangan": "Nama program di formulir ('[Nama Program Form]') berbeda dengan dokumen Agreement ('[Nama Program Agr]'). Harap sesuaikan dokumen Agreement."
       - Pada doc_validation.agr.message: "Nama program pada dokumen Agreement ('[Nama Program Agr]') berbeda dengan program yang diajukan ('[Nama Program Form]')."
   - Dokumen bukan dokumen resmi program atau file acak (foto selfie, nota sembarangan, dll):
     * "is_complete": false
     * "cek_dokumen": Sebutkan dokumen yang salah upload, contoh: "DOKUMEN TIDAK SESUAI (FAKTUR)"
     * "status_potong_purchase": "BELUM BISA POTONG"
     * "keterangan": "File yang diunggah pada slot tersebut bukan dokumen resmi yang diminta."

Format Keluaran:
Wajib mengembalikan JSON murni TANPA pembungkus markdown ```json ``` dengan key berikut:
{
  "dealer_name": string | null,
  "agr_dealer_name": string | null,
  "agr_program_name": string | null,
  "agr_dealer_mismatch": boolean,
  "agr_program_mismatch": boolean,
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
        $isPkp = app(ProgramReconciliationService::class)->isPkpFromSubmission($submission);

        return json_encode([
            'id_real' => $submission->id_real ?: '-',
            'dealer_name' => $submission->dealer_name ?: '-',
            'program_name' => $submission->program_name ?: '-',
            'sales_name' => $submission->sales_name ?: '-',
            'status_pajak' => $isPkp ? 'PKP (Badan/Pribadi PKP - Wajib Faktur Pajak)' : 'NON PKP (Orang Pribadi - Tidak Wajib Faktur Pajak, Hanya Wajib CN & AGR)',
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

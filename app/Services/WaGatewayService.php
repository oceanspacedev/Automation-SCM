<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WaGatewayService
{
    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) (config('services.waghub.url') ?: config('services.wag.url', 'https://waghub.mekayastudio.com')), '/');
        $this->token = (string) (config('services.waghub.token') ?: config('services.wag.token', ''));
    }

    /**
     * Kirim pesan teks via WAGhub.
     *
     * @param  string  $phone  Nomor tujuan (format: 628xxx atau 08xxx)
     */
    public function sendText(string $phone, string $message): bool
    {
        if (empty($this->baseUrl) || empty($this->token)) {
            Log::warning('WAGhub: URL atau token belum dikonfigurasi.');

            return false;
        }

        $normalized = $this->normalizePhone($phone);
        $uuid = Str::uuid()->toString();

        $payload = [
            'idempotency_key' => $uuid,
            'recipient' => [
                'type' => 'phone',
                'value' => $normalized,
            ],
            'message' => [
                'type' => 'text',
                'text' => $message,
            ],
            'purpose' => 'transactional',
            'mode' => 'async',
            'route_key' => 'default',
        ];

        try {
            $client = Http::withToken($this->token)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $uuid,
                ])
                ->timeout(20)
                ->retry(2, 500, throw: false);

            if (! config('services.wag.verify_ssl', false)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post("{$this->baseUrl}/api/v1/messages", $payload);

            if ($response->successful()) {
                Log::info("WAGhub: Pesan terkirim ke {$normalized}.");

                return true;
            }

            Log::warning("WAGhub: Gagal kirim ke {$normalized} (HTTP {$response->status()}): ".substr($response->body(), 0, 200));

            return false;
        } catch (\Throwable $e) {
            Log::warning("WAGhub: Exception saat kirim pesan: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Normalisasi nomor telepon → format internasional tanpa tanda +
     * Contoh: 081234 → 6281234 | 6281234 → 6281234
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '62')) {
            return '62'.$phone;
        }

        return $phone;
    }
}

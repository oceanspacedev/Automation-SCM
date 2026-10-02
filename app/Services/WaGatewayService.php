<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaGatewayService
{
    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.waghub.url', ''), '/');
        $this->token = (string) config('services.waghub.token', '');
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

        try {
            $response = Http::withoutVerifying()
                ->withToken($this->token)
                ->acceptJson()
                ->timeout(15)
                ->post("{$this->baseUrl}/api/v1/messages", [
                    'recipient' => [
                        'type' => 'phone',
                        'value' => $normalized,
                    ],
                    'message' => [
                        'type' => 'text',
                        'text' => $message,
                    ],
                ]);

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

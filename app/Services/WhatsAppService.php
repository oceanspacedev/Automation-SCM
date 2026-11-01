<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\ArContact;
use App\Models\DataProgram;
use App\Models\Invoice;
use App\Models\ProgramSubmission;
use App\Models\WhatsAppLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class WhatsAppService
{
    /**
     * How many days the confirmation link sent to telemarketing, AR and the tax team stays valid.
     */
    public const CONFIRM_LINK_DAYS = 30;

    /**
     * Send invoice notification message via WAGHub WhatsApp Gateway.
     *
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    public function sendInvoiceMessage(Invoice $invoice, ?string $overridePhone = null): array
    {
        $phone = $overridePhone ?: ($invoice->whatsapp ?: $invoice->draft?->whatsapp);
        $cleanPhone = $this->formatPhone($phone);

        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp tidak valid atau belum tersedia.',
                'provider_id' => null,
            ];
        }

        $apiUrl = rtrim(config('services.wag.url', 'https://waghub.mekayastudio.com'), '/').'/api/v1/messages';
        $token = config('services.wag.token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'WAG_TOKEN belum dikonfigurasi pada sistem.',
                'provider_id' => null,
            ];
        }

        $uuid = Str::uuid()->toString();
        $pdfUrl = $this->resolvePublicPdfUrl($invoice);
        $filename = "Invoice_{$invoice->invoice_number}.pdf";
        $messageText = mb_substr($this->buildInvoiceMessage($invoice, $pdfUrl), 0, 950);

        $payload = [
            'idempotency_key' => $uuid,
            'recipient' => [
                'type' => 'phone',
                'value' => $cleanPhone,
            ],
            'message' => [
                'type' => 'document',
                'text' => $messageText,
                'attachment' => [
                    'url' => $pdfUrl,
                    'filename' => $filename,
                    'mime_type' => 'application/pdf',
                ],
            ],
            'purpose' => 'transactional',
            'mode' => 'async',
            'route_key' => 'default',
            'client_reference' => $invoice->invoice_number,
        ];

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $uuid,
                ])
                ->timeout(25)
                ->retry(2, 500, throw: false);

            if (! config('services.wag.verify_ssl', false)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($apiUrl, $payload);

            $data = $response->json();
            $providerMessageId = $data['data']['provider_message_id'] ?? ($data['data']['id'] ?? null);

            if ($response->successful() && ($response->status() === 200 || $response->status() === 201)) {
                WhatsAppLog::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'recipient_phone' => $cleanPhone,
                    'status' => 'sent',
                    'provider_message_id' => $providerMessageId,
                    'error_message' => null,
                ]);

                return [
                    'success' => true,
                    'message' => "Pesan WhatsApp invoice {$invoice->invoice_number} berhasil dikirim ke {$cleanPhone}.",
                    'provider_id' => $providerMessageId,
                ];
            }

            $errorMessage = $data['message'] ?? ('HTTP Error '.$response->status());
            if (! empty($data['errors'])) {
                $errorMessage .= ' ('.json_encode($data['errors']).')';
            }

            WhatsAppLog::create([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipient_phone' => $cleanPhone,
                'status' => 'failed',
                'provider_message_id' => null,
                'error_message' => $errorMessage,
            ]);

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp: '.$errorMessage,
                'provider_id' => null,
            ];
        } catch (Exception $e) {
            WhatsAppLog::create([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipient_phone' => $cleanPhone,
                'status' => 'failed',
                'provider_message_id' => null,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception pengiriman WhatsApp: '.$e->getMessage(),
                'provider_id' => null,
            ];
        }
    }

    /**
     * Standardize Indonesian phone number to international 628... format.
     */
    public function formatPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove spaces, dots, dashes, parentheses
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));

        // If starts with +, remove +
        if (str_starts_with($cleaned, '+')) {
            $cleaned = substr($cleaned, 1);
        }

        // If starts with 08, replace with 628
        if (str_starts_with($cleaned, '08')) {
            $cleaned = '628'.substr($cleaned, 2);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '628'.substr($cleaned, 1);
        }

        // Basic sanity check: valid phone number must have at least 9 digits
        if (strlen($cleaned) < 9) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Build formatted Indonesian WhatsApp message for an invoice.
     */
    public function buildInvoiceMessage(Invoice $invoice, ?string $pdfUrl = null): string
    {
        $recipientName = $invoice->customer_name ?: $invoice->dealer_name;
        $dppFormatted = number_format((float) $invoice->dpp, 0, '.', ',');
        $netpayFormatted = number_format((float) $invoice->netpay, 0, '.', ',');

        if (empty($pdfUrl)) {
            $pdfUrl = $this->resolvePublicPdfUrl($invoice);
        }

        $lines = [
            '*INVOICE PEMBAYARAN - SCM*',
            'Kepada Yth.',
            "*{$recipientName}*",
            '',
            'Bersama ini kami sampaikan rincian invoice Anda:',
            "• *No. Invoice:* {$invoice->invoice_number}",
            '• *Tanggal:* '.($invoice->invoice_date ?: '-'),
            "• *Dealer:* {$invoice->dealer_name} ({$invoice->dealer_code})",
            '• *Customer:* '.($invoice->customer_name ?: '-'),
            '• *Program:* '.($invoice->program_name ?: '-'),
            '• *Periode:* '.($invoice->program_period ?: '-'),
            "• *DPP:* Rp {$dppFormatted}",
        ];

        if ((float) $invoice->dpp_lain > 0) {
            $lines[] = '• *DPP Lain:* Rp '.number_format((float) $invoice->dpp_lain, 0, '.', ',');
        }

        if ((float) $invoice->ppn > 0) {
            $lines[] = '• *PPN (12%):* Rp '.number_format((float) $invoice->ppn, 0, '.', ',');
        }

        if ((float) $invoice->pph > 0) {
            $lines[] = '• *PPh:* (Rp '.number_format((float) $invoice->pph, 0, '.', ',').')';
        }

        $lines[] = '--------------------------------------';
        $lines[] = "*Total Net Pay: Rp {$netpayFormatted}*";
        $lines[] = '--------------------------------------';
        $lines[] = 'Dokumen PDF terlampir atau dapat diakses via:';
        $lines[] = $pdfUrl;
        $lines[] = '';
        $lines[] = 'Catatan:';
        $lines[] = '1. Tanda tangan & cap maks. 30 hari sejak tanggal terbit.';
        $lines[] = '2. Untuk Dealer PKP, terbitkan Faktur Pajak sesuai tanggal berjalan jika lewat tgl 5.';
        $lines[] = '3. Dokumen '.$invoice->brandName().' upload ke: '.$invoice->documentUploadUrl();
        $lines[] = '';
        $lines[] = 'Terima kasih atas kerja sama Anda.';
        $lines[] = '';
        $lines[] = '_Ocean Space - Pesan otomatis sistem SCM Invoice_';

        return implode("\n", $lines);
    }

    /**
     * Resolve the base URL dynamically prioritizing customBase, public APP_URL, or incoming request host.
     */
    public function resolveBaseUrl(): string
    {
        $customBase = config('services.wag.public_url');
        if (! empty($customBase) && ! str_contains($customBase, 'localhost') && ! str_contains($customBase, '127.0.0.1')) {
            return rtrim($customBase, '/');
        }

        $appUrl = config('app.url');
        if (! empty($appUrl) && ! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '127.0.0.1')) {
            return rtrim($appUrl, '/');
        }

        try {
            if (function_exists('request') && request() && request()->hasHeader('Host')) {
                $reqHost = request()->getHttpHost();
                if (! empty($reqHost) && ! str_contains($reqHost, 'localhost') && ! str_contains($reqHost, '127.0.0.1')) {
                    return request()->getScheme().'://'.$reqHost;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return rtrim($customBase ?: ($appUrl ?: url('/')), '/');
    }

    /**
     * Resolve publicly accessible URL for the invoice PDF document.
     */
    public function resolvePublicPdfUrl(Invoice $invoice): string
    {
        // 1. If base URL is public (e.g. staging IP, domain, tunnel), use it
        $baseUrl = $this->resolveBaseUrl();
        if (! empty($baseUrl) && ! str_contains($baseUrl, 'localhost') && ! str_contains($baseUrl, '127.0.0.1')) {
            return "{$baseUrl}/invoices/{$invoice->id}/pdf";
        }

        // 2. For local development, mirror the PDF to a temporary public host so WAGHub can fetch the direct binary
        $mirrorUrl = $this->uploadToTemporaryPublicMirror($invoice);
        if (! empty($mirrorUrl)) {
            return $mirrorUrl;
        }

        return url("/invoices/{$invoice->id}/pdf");
    }

    /**
     * Upload invoice PDF content to temporary public mirror for local development.
     */
    protected function uploadToTemporaryPublicMirror(Invoice $invoice): ?string
    {
        try {
            $pdfContent = null;
            if ($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path)) {
                $pdfContent = Storage::disk('public')->get($invoice->pdf_path);
            } else {
                $viewName = match ($invoice->invoice_type) {
                    'DSA' => 'invoices.dsa',
                    'REGULAR', 'REGULER' => 'invoices.regular',
                    default => 'invoices.nps-fl',
                };
                $pdf = Pdf::loadView($viewName, ['invoice' => $invoice])->setPaper('a4', 'portrait');
                $pdfContent = $pdf->output();
            }

            if (empty($pdfContent)) {
                return null;
            }

            return $this->mirrorPublicly($pdfContent, "Invoice_{$invoice->invoice_number}.pdf");
        } catch (Exception $e) {
            Log::warning("Gagal mirror PDF invoice ke host publik: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Upload a PDF to a temporary public host (local development), so the WhatsApp gateway can fetch it.
     */
    public function mirrorPublicly(string $pdfContent, string $filename): ?string
    {
        try {
            // 1. Primary: Litterbox (Catbox.moe) - 72h retention, direct raw PDF binary stream
            try {
                $response = Http::withoutVerifying()
                    ->timeout(15)
                    ->attach('fileToUpload', $pdfContent, $filename)
                    ->post('https://litterbox.catbox.moe/resources/internals/api.php', [
                        'reqtype' => 'fileupload',
                        'time' => '72h',
                    ]);

                $url = trim($response->body());
                if ($response->successful() && str_starts_with($url, 'http')) {
                    return $url;
                }
            } catch (Exception $e) {
                Log::warning("Litterbox mirror gagal: {$e->getMessage()}");
            }

            // 2. Fallback: tmpfiles.org with regex-extracted direct download link
            try {
                $response = Http::withoutVerifying()
                    ->timeout(15)
                    ->attach('file', $pdfContent, $filename)
                    ->post('https://tmpfiles.org/api/v1/upload');

                if ($response->successful() && $rawUrl = $response->json('data.url')) {
                    $pageResponse = Http::withoutVerifying()->timeout(10)->get($rawUrl);
                    if ($pageResponse->successful() && preg_match('/<a[^>]+href="([^"]*dl\/[^"]+)"/i', $pageResponse->body(), $matches)) {
                        return $matches[1];
                    }
                }
            } catch (Exception $e) {
                Log::warning("tmpfiles fallback mirror gagal: {$e->getMessage()}");
            }
        } catch (Exception $e) {
            Log::warning("Gagal mirror PDF invoice ke host publik: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Send a PDF document with a caption to a WhatsApp number, e.g. the invoices of a region to its telemarketing.
     *
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    public function sendDocument(string $phone, string $text, string $pdfUrl, string $filename, string $clientReference): array
    {
        $cleanPhone = $this->formatPhone($phone);

        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp tidak valid atau belum tersedia.',
                'provider_id' => null,
            ];
        }

        $token = config('services.wag.token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'WAG_TOKEN belum dikonfigurasi pada sistem.',
                'provider_id' => null,
            ];
        }

        $uuid = Str::uuid()->toString();

        $payload = [
            'idempotency_key' => $uuid,
            'recipient' => [
                'type' => 'phone',
                'value' => $cleanPhone,
            ],
            'message' => [
                'type' => 'document',
                'text' => mb_substr($text, 0, 950),
                'attachment' => [
                    'url' => $pdfUrl,
                    'filename' => $filename,
                    'mime_type' => 'application/pdf',
                ],
            ],
            'purpose' => 'transactional',
            'mode' => 'async',
            'route_key' => 'default',
            'client_reference' => $clientReference,
        ];

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $uuid,
                ])
                ->timeout(25)
                ->retry(2, 500, throw: false);

            if (! config('services.wag.verify_ssl', false)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post(rtrim(config('services.wag.url', 'https://waghub.mekayastudio.com'), '/').'/api/v1/messages', $payload);
            $data = $response->json();

            if ($response->successful() && ($response->status() === 200 || $response->status() === 201)) {
                return [
                    'success' => true,
                    'message' => "{$filename} berhasil dikirim ke WhatsApp {$cleanPhone}.",
                    'provider_id' => $data['data']['provider_message_id'] ?? ($data['data']['id'] ?? null),
                ];
            }

            $errorMessage = $data['message'] ?? ('HTTP Error '.$response->status());
            if (! empty($data['errors'])) {
                $errorMessage .= ' ('.json_encode($data['errors']).')';
            }

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp: '.$errorMessage,
                'provider_id' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception pengiriman WhatsApp: '.$e->getMessage(),
                'provider_id' => null,
            ];
        }
    }

    /**
     * Build the WhatsApp to a region's telemarketing that comes with one PDF of all its invoices.
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    public function buildRegionInvoiceMessage(string $region, ?string $telemarketingName, Collection $invoices, string $pdfUrl): string
    {
        $rupiah = fn ($amount) => 'Rp '.number_format((float) $amount, 0, '.', ',');
        $shown = $invoices->take(15)->values();

        $lines = [
            '*INVOICE PEMBAYARAN - SCM*',
            "*Region {$region}* · {$invoices->count()} invoice",
            'Kepada Yth. '.($telemarketingName ?: 'Tim Telemarketing'),
            '',
        ];

        foreach ($shown as $index => $invoice) {
            $lines[] = ($index + 1).". {$invoice->invoice_number} · {$invoice->dealer_name} · {$rupiah($invoice->netpay)}";
        }
        if ($invoices->count() > $shown->count()) {
            $lines[] = '… dan '.($invoices->count() - $shown->count()).' invoice lainnya (lihat PDF).';
        }

        $first = $invoices->first();

        return implode("\n", [
            ...$lines,
            '',
            '*Total Net Pay: '.$rupiah($invoices->sum(fn (Invoice $invoice) => (float) $invoice->netpay)).'*',
            'Semua invoice ada di PDF terlampir (satu invoice per halaman), atau via:',
            $pdfUrl,
            '',
            'Catatan:',
            '1. Tanda tangan & cap maks. 30 hari sejak tanggal terbit.',
            '2. Untuk Dealer PKP, terbitkan Faktur Pajak sesuai tanggal berjalan jika lewat tgl 5.',
            '3. Dokumen '.$first->brandName().' upload ke: '.$first->documentUploadUrl(),
            '',
            '_Ocean Space - Pesan otomatis sistem SCM Invoice_',
        ]);
    }

    /**
     * Send program claim notification message to Telemarketing with 1-click action links.
     *
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    public function sendProgramClaimNotificationToTelemarketing(ProgramSubmission $submission, ?string $overridePhone = null): array
    {
        $phone = $overridePhone ?: (config('services.wag.telemarketing_phone') ?: config('services.wag.ar_phone', '081224290502'));
        $cleanPhone = $this->formatPhone($phone);

        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp Telemarketing tidak valid atau belum disetel.',
                'provider_id' => null,
            ];
        }

        $apiUrl = rtrim(config('services.wag.url', 'https://waghub.mekayastudio.com'), '/').'/api/v1/messages';
        $token = config('services.wag.token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'WAG_TOKEN belum dikonfigurasi pada sistem.',
                'provider_id' => null,
            ];
        }

        $baseUrl = $this->resolveBaseUrl();
        if (! empty($baseUrl)) {
            URL::forceRootUrl($baseUrl);
        }

        // Generate signed confirmation URL valid for CONFIRM_LINK_DAYS days
        $confirmUrl = URL::temporarySignedRoute(
            'program-submissions.confirm',
            now()->addDays(self::CONFIRM_LINK_DAYS),
            ['id' => $submission->id, 'role' => 'telemarketing']
        );

        $messageText = $this->buildProgramClaimTelemarketingMessage($submission, $confirmUrl);
        $uuid = Str::uuid()->toString();

        $payload = [
            'idempotency_key' => $uuid,
            'recipient' => [
                'type' => 'phone',
                'value' => $cleanPhone,
            ],
            'message' => [
                'type' => 'text',
                'text' => $messageText,
            ],
            'purpose' => 'transactional',
            'mode' => 'async',
            'route_key' => 'default',
            'client_reference' => "PROGRAM-CLAIM-TM-{$submission->id}",
        ];

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $uuid,
                ])
                ->timeout(25)
                ->retry(2, 500, throw: false);

            if (! config('services.wag.verify_ssl', false)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($apiUrl, $payload);
            $data = $response->json();
            $providerMessageId = $data['data']['provider_message_id'] ?? ($data['data']['id'] ?? null);

            if ($response->successful() && ($response->status() === 200 || $response->status() === 201)) {
                return [
                    'success' => true,
                    'message' => "Notifikasi klaim program {$submission->dealer_name} berhasil dikirim ke WhatsApp Telemarketing ({$cleanPhone}).",
                    'provider_id' => $providerMessageId,
                ];
            }

            $errorMessage = $data['message'] ?? ('HTTP Error '.$response->status());
            if (! empty($data['errors'])) {
                $errorMessage .= ' ('.json_encode($data['errors']).')';
            }

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp ke Telemarketing: '.$errorMessage,
                'provider_id' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception pengiriman WhatsApp ke Telemarketing: '.$e->getMessage(),
                'provider_id' => null,
            ];
        }
    }

    /**
     * Send program claim notification message to AR with 1-click action links.
     *
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    public function sendProgramClaimNotificationToAr(ProgramSubmission $submission, ?string $overridePhone = null): array
    {
        // AR tujuan mengikuti region dealer di Master AR; region yang belum punya AR memakai nomor AR default
        $arContact = $overridePhone ? null : ArContact::forRegion($submission->areaEnum(), $submission->region);
        $phone = $overridePhone ?: ($arContact?->whatsapp ?: config('services.wag.ar_phone', '081224290502'));
        $cleanPhone = $this->formatPhone($phone);

        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp AR tidak valid atau belum disetel.',
                'provider_id' => null,
            ];
        }

        $apiUrl = rtrim(config('services.wag.url', 'https://waghub.mekayastudio.com'), '/').'/api/v1/messages';
        $token = config('services.wag.token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'WAG_TOKEN belum dikonfigurasi pada sistem.',
                'provider_id' => null,
            ];
        }

        $baseUrl = $this->resolveBaseUrl();
        if (! empty($baseUrl)) {
            URL::forceRootUrl($baseUrl);
        }

        // Generate signed confirmation URL valid for CONFIRM_LINK_DAYS days
        $confirmUrl = URL::temporarySignedRoute(
            'program-submissions.confirm',
            now()->addDays(self::CONFIRM_LINK_DAYS),
            ['id' => $submission->id, 'role' => 'ar']
        );

        $messageText = $this->buildProgramClaimMessage($submission, $confirmUrl, $arContact?->name);
        $uuid = Str::uuid()->toString();

        $payload = [
            'idempotency_key' => $uuid,
            'recipient' => [
                'type' => 'phone',
                'value' => $cleanPhone,
            ],
            'message' => [
                'type' => 'text',
                'text' => $messageText,
            ],
            'purpose' => 'transactional',
            'mode' => 'async',
            'route_key' => 'default',
            'client_reference' => "PROGRAM-CLAIM-{$submission->id}",
        ];

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $uuid,
                ])
                ->timeout(25)
                ->retry(2, 500, throw: false);

            if (! config('services.wag.verify_ssl', false)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($apiUrl, $payload);
            $data = $response->json();
            $providerMessageId = $data['data']['provider_message_id'] ?? ($data['data']['id'] ?? null);

            if ($response->successful() && ($response->status() === 200 || $response->status() === 201)) {
                return [
                    'success' => true,
                    'message' => "Notifikasi klaim program {$submission->dealer_name} berhasil dikirim ke WhatsApp AR".($arContact ? " {$arContact->name}" : '')." ({$cleanPhone}).",
                    'provider_id' => $providerMessageId,
                ];
            }

            $errorMessage = $data['message'] ?? ('HTTP Error '.$response->status());
            if (! empty($data['errors'])) {
                $errorMessage .= ' ('.json_encode($data['errors']).')';
            }

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp ke AR: '.$errorMessage,
                'provider_id' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception pengiriman WhatsApp ke AR: '.$e->getMessage(),
                'provider_id' => null,
            ];
        }
    }

    /**
     * Build formatted Indonesian WhatsApp message for Telemarketing program claim offer.
     */
    public function buildProgramClaimTelemarketingMessage(ProgramSubmission $submission, string $confirmUrl): string
    {
        $dealerName = $submission->dealer_name ?: '-';
        $idReal = $submission->id_real ?: '-';
        $programName = $submission->program_name ?: '-';
        $region = $submission->region ?: '-';
        $salesName = $submission->sales_name ?: '-';
        $cekDokumen = $submission->cek_dokumen ?: 'LENGKAP';
        $netPayFormatted = 'Rp '.number_format($submission->net_pay ?? 0, 0, ',', '.');

        $lines = [
            '*PEMBERITAHUAN KLAIM BISA DIPOTONG (INFO TELEMARKETING)*',
            '',
            'Halo Tim Telemarketing / Sales, dokumen klaim program berikut telah LENGKAP dan SIAP DITAWARKAN POTONG ke dealer saat order:',
            '',
            "*ID Real:* {$idReal}",
            "*Dealer:* {$dealerName}",
            "*Region:* {$region}",
            "*Program:* {$programName}",
            "*Sales:* {$salesName}",
            "*Nominal Potongan (Net Pay):* {$netPayFormatted}",
            "*Status Dokumen:* {$cekDokumen}",
            '*Status Purchase:* BISA DI POTONG',
            '',
            'Silakan hubungi dealer dan tawarkan potongan saldo insentif ini pada invoice order mereka.',
            'Buka tautan di bawah ini untuk mengonfirmasi kesediaan dealer (Iya / Tidak):',
            '',
            '👉 *[ KLIK: KONFIRMASI KEPUTUSAN DEALER ]*',
            $confirmUrl,
            '',
            '_(Tautan ini aktif selama '.self::CONFIRM_LINK_DAYS.' hari)_',
            '_Pesan otomatis dari Sistem SCM_',
        ];

        return implode("\n", $lines);
    }

    /**
     * Build formatted Indonesian WhatsApp message for AR program claim confirmation.
     */
    public function buildProgramClaimMessage(ProgramSubmission $submission, string $confirmUrl, ?string $arName = null): string
    {
        $dealerName = $submission->dealer_name ?: '-';
        $idReal = $submission->id_real ?: '-';
        $programName = $submission->program_name ?: '-';
        $region = $submission->region ?: '-';
        $salesName = $submission->sales_name ?: '-';
        $cekDokumen = $submission->cek_dokumen ?: 'LENGKAP';
        $netPayFormatted = 'Rp '.number_format($submission->net_pay ?? 0, 0, ',', '.');

        $lines = [
            '*PEMBERITAHUAN KLAIM TELAH DISETUJUI DEALER (UNTUK TIM AR)*',
            '',
            'Halo '.($arName ?: 'Tim AR').', Telemarketing telah mengonfirmasi bahwa dealer berikut SETUJU untuk dipotongkan pada order pembelian mereka:',
            '',
            "*ID Real:* {$idReal}",
            "*Dealer:* {$dealerName}",
            "*Region:* {$region}",
            "*Program:* {$programName}",
            "*Sales:* {$salesName}",
            "*Nominal Potongan (Net Pay):* {$netPayFormatted}",
            "*Status Dokumen:* {$cekDokumen}",
            '*Status Klaim:* DEALER SETUJU DIPOTONG',
            '',
            'Silakan potongkan saldo piutang dealer pada invoice order mereka.',
            'Jika sudah selesai dipotong atau ingin konfirmasi, silakan klik tautan di bawah ini:',
            '',
            '👉 *[ KLIK: KONFIRMASI PEMOTONGAN AR ]*',
            $confirmUrl,
            '',
            '_(Tautan ini aktif selama '.self::CONFIRM_LINK_DAYS.' hari)_',
            '_Pesan otomatis dari Sistem SCM_',
        ];

        return implode("\n", $lines);
    }

    /**
     * Send DataProgram claim notification to Telemarketing with 1-click confirmation link.
     * With a reminder number, the message is that reminder for a row the dealer has not approved yet.
     *
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    public function sendDataProgramClaimNotificationToTelemarketing(DataProgram $dp, ?string $overridePhone = null, ?int $reminderNumber = null): array
    {
        $phone = $overridePhone ?: (config('services.wag.telemarketing_phone') ?: config('services.wag.ar_phone', '081224290502'));
        $cleanPhone = $this->formatPhone($phone);

        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp Telemarketing tidak valid atau belum disetel.',
                'provider_id' => null,
            ];
        }

        $apiUrl = rtrim(config('services.wag.url', 'https://waghub.mekayastudio.com'), '/').'/api/v1/messages';
        $token = config('services.wag.token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'WAG_TOKEN belum dikonfigurasi pada sistem.',
                'provider_id' => null,
            ];
        }

        $baseUrl = $this->resolveBaseUrl();
        if (! empty($baseUrl)) {
            URL::forceRootUrl($baseUrl);
        }

        $confirmUrl = URL::temporarySignedRoute(
            'data-programs.confirm',
            now()->addDays(self::CONFIRM_LINK_DAYS),
            ['id' => $dp->id, 'role' => 'telemarketing']
        );

        $messageText = $this->buildDataProgramClaimTelemarketingMessage($dp, $confirmUrl, $reminderNumber);
        $uuid = Str::uuid()->toString();

        $payload = [
            'idempotency_key' => $uuid,
            'recipient' => [
                'type' => 'phone',
                'value' => $cleanPhone,
            ],
            'message' => [
                'type' => 'text',
                'text' => $messageText,
            ],
            'purpose' => 'transactional',
            'mode' => 'async',
            'route_key' => 'default',
            'client_reference' => "DP-CLAIM-TM-{$dp->id}".($reminderNumber ? "-R{$reminderNumber}" : ''),
        ];

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $uuid,
                ])
                ->timeout(25)
                ->retry(2, 500, throw: false);

            if (! config('services.wag.verify_ssl', false)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($apiUrl, $payload);
            $data = $response->json();
            $providerMessageId = $data['data']['provider_message_id'] ?? ($data['data']['id'] ?? null);

            if ($response->successful() && ($response->status() === 200 || $response->status() === 201)) {
                return [
                    'success' => true,
                    'message' => "Notifikasi klaim program {$dp->dealer_name} berhasil dikirim ke WhatsApp Telemarketing ({$cleanPhone}).",
                    'provider_id' => $providerMessageId,
                ];
            }

            $errorMessage = $data['message'] ?? ('HTTP Error '.$response->status());
            if (! empty($data['errors'])) {
                $errorMessage .= ' ('.json_encode($data['errors']).')';
            }

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp ke Telemarketing: '.$errorMessage,
                'provider_id' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception pengiriman WhatsApp ke Telemarketing: '.$e->getMessage(),
                'provider_id' => null,
            ];
        }
    }

    /**
     * Send DataProgram claim notification to AR with 1-click confirmation link.
     *
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    public function sendDataProgramClaimNotificationToAr(DataProgram $dp, ?string $overridePhone = null): array
    {
        // AR tujuan mengikuti region (lalu big region) di Master AR; region yang belum punya AR memakai nomor AR default
        $arContact = $overridePhone ? null : ArContact::forRegion($dp->areaEnum(), $dp->region, $dp->big_region);
        $phone = $overridePhone ?: ($arContact?->whatsapp ?: config('services.wag.ar_phone', '081224290502'));
        $cleanPhone = $this->formatPhone($phone);

        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp AR tidak valid atau belum disetel.',
                'provider_id' => null,
            ];
        }

        $apiUrl = rtrim(config('services.wag.url', 'https://waghub.mekayastudio.com'), '/').'/api/v1/messages';
        $token = config('services.wag.token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'WAG_TOKEN belum dikonfigurasi pada sistem.',
                'provider_id' => null,
            ];
        }

        $baseUrl = $this->resolveBaseUrl();
        if (! empty($baseUrl)) {
            URL::forceRootUrl($baseUrl);
        }

        $confirmUrl = URL::temporarySignedRoute(
            'data-programs.confirm',
            now()->addDays(self::CONFIRM_LINK_DAYS),
            ['id' => $dp->id, 'role' => 'ar']
        );

        $messageText = $this->buildDataProgramClaimArMessage($dp, $confirmUrl, $arContact?->name);
        $uuid = Str::uuid()->toString();

        $payload = [
            'idempotency_key' => $uuid,
            'recipient' => [
                'type' => 'phone',
                'value' => $cleanPhone,
            ],
            'message' => [
                'type' => 'text',
                'text' => $messageText,
            ],
            'purpose' => 'transactional',
            'mode' => 'async',
            'route_key' => 'default',
            'client_reference' => "DP-CLAIM-AR-{$dp->id}",
        ];

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $uuid,
                ])
                ->timeout(25)
                ->retry(2, 500, throw: false);

            if (! config('services.wag.verify_ssl', false)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($apiUrl, $payload);
            $data = $response->json();
            $providerMessageId = $data['data']['provider_message_id'] ?? ($data['data']['id'] ?? null);

            if ($response->successful() && ($response->status() === 200 || $response->status() === 201)) {
                return [
                    'success' => true,
                    'message' => "Notifikasi klaim program {$dp->dealer_name} berhasil dikirim ke WhatsApp AR".($arContact ? " {$arContact->name}" : '')." ({$cleanPhone}).",
                    'provider_id' => $providerMessageId,
                ];
            }

            $errorMessage = $data['message'] ?? ('HTTP Error '.$response->status());
            if (! empty($data['errors'])) {
                $errorMessage .= ' ('.json_encode($data['errors']).')';
            }

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp ke AR: '.$errorMessage,
                'provider_id' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception pengiriman WhatsApp ke AR: '.$e->getMessage(),
                'provider_id' => null,
            ];
        }
    }

    /**
     * Ask the area's tax team (Master AR → Tim Pajak) to check the PPh of a claim AR has cut, with a link to fill in
     * Cek Pajak, Selisih and Note PPh.
     *
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    public function sendDataProgramTaxCheckNotification(DataProgram $dp, ?string $overridePhone = null): array
    {
        $baseUrl = $this->resolveBaseUrl();
        if (! empty($baseUrl)) {
            URL::forceRootUrl($baseUrl);
        }

        $confirmUrl = URL::temporarySignedRoute(
            'data-programs.confirm',
            now()->addDays(self::CONFIRM_LINK_DAYS),
            ['id' => $dp->id, 'role' => 'pajak']
        );

        return $this->sendToTeam($dp, AppSetting::TAX_TEAM, 'Tim Pajak', $overridePhone, "DP-TAX-CHECK-{$dp->id}",
            fn (?string $name) => $this->buildDataProgramTaxCheckMessage($dp, $confirmUrl, $name),
            "Permintaan cek pajak {$dp->dealer_name}");
    }

    /**
     * Send the result of the tax check of a cut claim (Cek Pajak, Selisih, Note PPh) to the area's SCM team
     * (Master AR → Tim SCM).
     *
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    public function sendDataProgramScmNotification(DataProgram $dp, ?string $overridePhone = null): array
    {
        return $this->sendToTeam($dp, AppSetting::SCM_TEAM, 'Tim SCM', $overridePhone, "DP-SCM-RESULT-{$dp->id}",
            fn (?string $name) => $this->buildDataProgramScmMessage($dp, $name),
            "Hasil cek pajak {$dp->dealer_name}");
    }

    /**
     * Send a Data Program message to the area's tax or SCM team in Master AR (or the given number).
     *
     * @param  \Closure(?string): string  $buildMessage  the message for the team's name
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    protected function sendToTeam(DataProgram $dp, string $team, string $teamLabel, ?string $overridePhone, string $clientReference, \Closure $buildMessage, string $subject): array
    {
        $contact = AppSetting::teamContact($dp->areaEnum(), $team);
        $cleanPhone = $this->formatPhone($overridePhone ?: $contact['whatsapp']);

        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'message' => "Nomor WhatsApp {$teamLabel} belum diisi di Master AR.",
                'provider_id' => null,
            ];
        }

        $token = config('services.wag.token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'WAG_TOKEN belum dikonfigurasi pada sistem.',
                'provider_id' => null,
            ];
        }

        $uuid = Str::uuid()->toString();

        $payload = [
            'idempotency_key' => $uuid,
            'recipient' => [
                'type' => 'phone',
                'value' => $cleanPhone,
            ],
            'message' => [
                'type' => 'text',
                'text' => $buildMessage($contact['name']),
            ],
            'purpose' => 'transactional',
            'mode' => 'async',
            'route_key' => 'default',
            'client_reference' => $clientReference,
        ];

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $uuid,
                ])
                ->timeout(25)
                ->retry(2, 500, throw: false);

            if (! config('services.wag.verify_ssl', false)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post(rtrim(config('services.wag.url', 'https://waghub.mekayastudio.com'), '/').'/api/v1/messages', $payload);
            $data = $response->json();

            if ($response->successful() && ($response->status() === 200 || $response->status() === 201)) {
                return [
                    'success' => true,
                    'message' => "{$subject} berhasil dikirim ke WhatsApp {$teamLabel}".($contact['name'] && ! $overridePhone ? " {$contact['name']}" : '')." ({$cleanPhone}).",
                    'provider_id' => $data['data']['provider_message_id'] ?? ($data['data']['id'] ?? null),
                ];
            }

            $errorMessage = $data['message'] ?? ('HTTP Error '.$response->status());
            if (! empty($data['errors'])) {
                $errorMessage .= ' ('.json_encode($data['errors']).')';
            }

            return [
                'success' => false,
                'message' => "Gagal mengirim pesan WhatsApp ke {$teamLabel}: ".$errorMessage,
                'provider_id' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Exception pengiriman WhatsApp ke {$teamLabel}: ".$e->getMessage(),
                'provider_id' => null,
            ];
        }
    }

    /**
     * Build the WhatsApp asking the tax team to check the PPh of a claim AR has cut.
     */
    public function buildDataProgramTaxCheckMessage(DataProgram $dp, string $confirmUrl, ?string $taxTeamName = null): string
    {
        $rupiah = fn ($amount) => 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
        $npwp = trim(($dp->npwp ?: '').' '.($dp->nama_npwp ? "({$dp->nama_npwp})" : ''));

        $lines = [
            '*CEK PAJAK KLAIM SUDAH DIPOTONG (DATA PROGRAM)*',
            '',
            'Halo '.($taxTeamName ?: 'Tim Pajak').', klaim program berikut sudah DIPOTONG oleh AR. Mohon cek PPh-nya, lalu isi Cek Pajak, Selisih, dan Note PPh lewat tautan di bawah:',
            '',
            '*Kode BT / ID Real:* '.($dp->kode_bt ?: '-'),
            '*Dealer:* '.($dp->dealer_name ?: '-'),
            '*Region:* '.($dp->region ?: ($dp->big_region ?: '-')),
            '*Program:* '.($dp->program_name ?: ($dp->program ?: '-')),
            '*Wajib Pajak / Tarif PPh:* '.($dp->wajib_pajak ?: '-').' / '.($dp->trf_pph ?: '-'),
            '*NPWP:* '.($npwp ?: '-'),
            '*DPP:* '.$rupiah($dp->dpp),
            '*PPN:* '.$rupiah($dp->ppn),
            '*PPh:* '.$rupiah($dp->nilai_pph),
            '*Net Pay:* '.$rupiah($dp->net_pay),
            '*Tgl Potong / No. Pembayaran:* '.($dp->tgl_potong_tf ?: '-').' / '.($dp->no_pembayaran ?: '-'),
            '',
            '👉 *[ KLIK: ISI CEK PAJAK ]*',
            $confirmUrl,
            '',
            '_(Tautan ini aktif selama '.self::CONFIRM_LINK_DAYS.' hari)_',
            '_Pesan otomatis dari Sistem SCM_',
        ];

        return implode("\n", $lines);
    }

    /**
     * Build the WhatsApp telling the SCM team the result of the tax check of a cut claim.
     */
    public function buildDataProgramScmMessage(DataProgram $dp, ?string $scmTeamName = null): string
    {
        $rupiah = fn ($amount) => 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');

        $lines = [
            '*HASIL CEK PAJAK KLAIM (DATA PROGRAM)*',
            '',
            'Halo '.($scmTeamName ?: 'Tim SCM').', Tim Pajak sudah mengecek PPh klaim yang sudah dipotong AR berikut:',
            '',
            '*Kode BT / ID Real:* '.($dp->kode_bt ?: '-'),
            '*Dealer:* '.($dp->dealer_name ?: '-'),
            '*Region:* '.($dp->region ?: ($dp->big_region ?: '-')),
            '*Program:* '.($dp->program_name ?: ($dp->program ?: '-')),
            '*Net Pay:* '.$rupiah($dp->net_pay),
            '*Tgl Potong / No. Pembayaran:* '.($dp->tgl_potong_tf ?: '-').' / '.($dp->no_pembayaran ?: '-'),
            '*PPh:* '.$rupiah($dp->nilai_pph),
            '',
            '*Cek Pajak:* '.($dp->cek_pajak_tarif ?: '-'),
            '*Selisih:* '.$rupiah($dp->selisih),
            '*Note PPh:* '.($dp->note_pph ?: '-'),
            '*Dicek:* '.($dp->tax_checked_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i')).' WIB',
            '',
            '_Pesan otomatis dari Sistem SCM_',
        ];

        return implode("\n", $lines);
    }

    /**
     * Build formatted Indonesian WhatsApp message for Telemarketing DataProgram claim offer
     * (or, with a reminder number, the reminder while the dealer has not approved it).
     */
    public function buildDataProgramClaimTelemarketingMessage(DataProgram $dp, string $confirmUrl, ?int $reminderNumber = null): string
    {
        $dealerName = $dp->dealer_name ?: '-';
        $kodeBt = $dp->kode_bt ?: '-';
        $programName = $dp->program_name ?: ($dp->program ?: '-');
        $region = $dp->region ?: ($dp->big_region ?: '-');
        $salesName = $dp->sales_person ?: '-';
        $cekDokumen = $dp->cek_dokumen ?: 'LENGKAP';
        $netPayFormatted = 'Rp '.number_format((float) ($dp->net_pay ?? 0), 0, ',', '.');

        $intro = match (true) {
            $reminderNumber === null => 'Halo Tim Telemarketing / Sales, dokumen klaim program berikut telah LENGKAP dan SIAP DITAWARKAN POTONG ke dealer saat order:',
            $dp->status_potong_ar === 'PENDING DEALER' => 'Halo Tim Telemarketing / Sales, klaim program berikut sebelumnya DITUNDA dealer dan sampai sekarang belum disetujui. Mohon tawarkan kembali potongannya:',
            default => 'Halo Tim Telemarketing / Sales, klaim program berikut sudah BISA DIPOTONG tetapi belum ada konfirmasi dari dealer:',
        };

        $lines = [
            $reminderNumber === null
                ? '*PEMBERITAHUAN KLAIM BISA DIPOTONG (DATA PROGRAM)*'
                : "*PENGINGAT KE-{$reminderNumber}: KLAIM BELUM DISETUJUI DEALER (DATA PROGRAM)*",
            '',
            $intro,
            '',
            "*Kode BT / ID Real:* {$kodeBt}",
            "*Dealer:* {$dealerName}",
            "*Region:* {$region}",
            "*Program:* {$programName}",
            "*Sales:* {$salesName}",
            "*Nominal Potongan (Net Pay):* {$netPayFormatted}",
            "*Status Dokumen:* {$cekDokumen}",
            '*Status Purchase:* BISA DI POTONG',
            '',
            'Silakan hubungi dealer dan tawarkan potongan saldo insentif ini pada invoice order mereka.',
            'Buka tautan di bawah ini untuk mengonfirmasi kesediaan dealer (Iya / Tidak):',
            '',
            '👉 *[ KLIK: KONFIRMASI KEPUTUSAN DEALER ]*',
            $confirmUrl,
            '',
            '_(Tautan ini aktif selama '.self::CONFIRM_LINK_DAYS.' hari)_',
            '_Pesan otomatis dari Sistem SCM_',
        ];

        return implode("\n", $lines);
    }

    /**
     * Build formatted Indonesian WhatsApp message for AR DataProgram claim confirmation.
     */
    public function buildDataProgramClaimArMessage(DataProgram $dp, string $confirmUrl, ?string $arName = null): string
    {
        $dealerName = $dp->dealer_name ?: '-';
        $kodeBt = $dp->kode_bt ?: '-';
        $programName = $dp->program_name ?: ($dp->program ?: '-');
        $region = $dp->region ?: ($dp->big_region ?: '-');
        $salesName = $dp->sales_person ?: '-';
        $netPayFormatted = 'Rp '.number_format((float) ($dp->net_pay ?? 0), 0, ',', '.');

        $lines = [
            '*PEMBERITAHUAN POTONG KLAIM (TIM AR)*',
            '',
            'Halo '.($arName ?: 'Tim AR').', dealer telah MENYETUJUI pemotongan saldo insentif program berikut pada invoice order mereka:',
            '',
            "*Kode BT / ID Real:* {$kodeBt}",
            "*Dealer:* {$dealerName}",
            "*Region:* {$region}",
            "*Program:* {$programName}",
            "*Sales:* {$salesName}",
            "*Nominal Potongan (Net Pay):* {$netPayFormatted}",
            '*Status Telemarketing:* DEALER SETUJU (PROSES AR)',
            '',
            'Mohon diproses pemotongan pada tagihan dealer. Jika sudah selesai dipotong, silakan klik tautan di bawah ini:',
            '',
            '👉 *[ KLIK: KONFIRMASI PEMOTONGAN AR ]*',
            $confirmUrl,
            '',
            '_(Tautan ini aktif selama '.self::CONFIRM_LINK_DAYS.' hari)_',
            '_Pesan otomatis dari Sistem SCM_',
        ];

        return implode("\n", $lines);
    }

    /**
     * Send notification to sales via WhatsApp regarding invalid or swapped documents.
     *
     * @return array{success: bool, message: string, provider_id: ?string, wa_url: ?string, recipient_phone: ?string}
     */
    public function sendProgramDocumentErrorNotification(ProgramSubmission $submission, ?string $overridePhone = null): array
    {
        $phone = $overridePhone ?: ($submission->whatsapp ?: config('services.wag.ar_phone', '081224290502'));
        $cleanPhone = $this->formatPhone($phone);

        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp penerima tidak valid atau belum disetel.',
                'provider_id' => null,
                'wa_url' => null,
                'recipient_phone' => null,
            ];
        }

        $messageText = $this->buildProgramDocumentErrorMessage($submission);
        $encodedText = rawurlencode($messageText);
        $waUrl = "https://api.whatsapp.com/send?phone={$cleanPhone}&text={$encodedText}";

        $apiUrl = rtrim(config('services.wag.url', 'https://waghub.mekayastudio.com'), '/').'/api/v1/messages';
        $token = config('services.wag.token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'WAG_TOKEN belum dikonfigurasi pada sistem.',
                'provider_id' => null,
                'wa_url' => $waUrl,
                'recipient_phone' => $cleanPhone,
            ];
        }

        $uuid = Str::uuid()->toString();
        $payload = [
            'idempotency_key' => $uuid,
            'recipient' => [
                'type' => 'phone',
                'value' => $cleanPhone,
            ],
            'message' => [
                'type' => 'text',
                'text' => $messageText,
            ],
            'purpose' => 'transactional',
            'mode' => 'async',
            'route_key' => 'default',
            'client_reference' => "DOC-ERROR-SUB-{$submission->id}",
        ];

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Idempotency-Key' => $uuid,
                ])
                ->timeout(25)
                ->retry(2, 500, throw: false);

            if (! config('services.wag.verify_ssl', false)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($apiUrl, $payload);
            $data = $response->json();
            $providerMessageId = $data['data']['provider_message_id'] ?? ($data['data']['id'] ?? null);

            if ($response->successful() && ($response->status() === 200 || $response->status() === 201)) {
                Log::info("WA Notifikasi Dokumen Salah berhasil dikirim untuk Submission ID {$submission->id} ke {$cleanPhone}. Provider ID: {$providerMessageId}");

                return [
                    'success' => true,
                    'message' => "Notifikasi dokumen salah untuk {$submission->dealer_name} berhasil dikirim ke WhatsApp ({$cleanPhone}).",
                    'provider_id' => $providerMessageId,
                    'wa_url' => $waUrl,
                    'recipient_phone' => $cleanPhone,
                ];
            }

            $errorMessage = $data['message'] ?? ('HTTP Error '.$response->status());
            if (! empty($data['errors'])) {
                $errorMessage .= ' ('.json_encode($data['errors']).')';
            }

            Log::warning("Gagal mengirim WA dokumen salah untuk Submission ID {$submission->id} ke {$cleanPhone}: {$errorMessage}");

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp: '.$errorMessage,
                'provider_id' => null,
                'wa_url' => $waUrl,
                'recipient_phone' => $cleanPhone,
            ];
        } catch (Exception $e) {
            Log::error("Exception pengiriman WA dokumen salah Submission ID {$submission->id}: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Exception pengiriman WhatsApp: '.$e->getMessage(),
                'provider_id' => null,
                'wa_url' => $waUrl,
                'recipient_phone' => $cleanPhone,
            ];
        }
    }

    /**
     * Build formatted Indonesian WhatsApp message for incorrect/invalid program documents.
     */
    public function buildProgramDocumentErrorMessage(ProgramSubmission $submission): string
    {
        $salesGreeting = ! empty($submission->sales_name)
            ? "Halo Kak {$submission->sales_name},"
            : 'Halo Rekan Sales / Dealer,';

        $dealerName = $submission->dealer_name ?: '-';
        $idReal = $submission->id_real ?: '-';
        $programName = $submission->program_name ?: '-';
        $region = $submission->region ?: '-';

        $docValidation = $submission->doc_validation ?? [];
        $issues = [];

        // Check CN
        if (isset($docValidation['cn'])) {
            $cn = $docValidation['cn'];
            if (($cn['status'] ?? '') === 'invalid') {
                $msg = $cn['message'] ?? 'Dokumen tidak sesuai.';
                $issues[] = "- Credit Note (CN): {$msg} Mohon sesuaikan nama program dengan isi CN.";
            } elseif (($cn['status'] ?? '') === 'swapped') {
                $actual = strtoupper($cn['actual_type'] ?? 'dokumen lain');
                $issues[] = "- Credit Note (CN): File di kolom CN tertukar (terdeteksi berisi {$actual}). Mohon sesuaikan nama program dengan isi CN dan unggah file CN yang benar.";
            }
        }

        // Check Agr
        if (isset($docValidation['agr'])) {
            $agr = $docValidation['agr'];
            if (($agr['status'] ?? '') === 'invalid') {
                $msg = $agr['message'] ?? 'Dokumen tidak sesuai.';
                $issues[] = "- Agreement (Agr): {$msg} Begitu juga dengan Agreement, mohon sesuaikan dengan program yang diajukan.";
            } elseif (($agr['status'] ?? '') === 'swapped') {
                $actual = strtoupper($agr['actual_type'] ?? 'dokumen lain');
                $issues[] = "- Agreement (Agr): File di kolom Agr tertukar (terdeteksi berisi {$actual}). Begitu juga dengan Agreement, mohon unggah file Agreement yang sesuai.";
            }
        }

        // Check Faktur
        if (isset($docValidation['faktur'])) {
            $faktur = $docValidation['faktur'];
            if (($faktur['status'] ?? '') === 'invalid') {
                $msg = $faktur['message'] ?? 'Dokumen tidak sesuai.';
                $issues[] = "- Faktur Pajak (FP): {$msg} Begitu juga dengan Faktur Pajak, mohon sesuaikan dengan program yang diajukan.";
            } elseif (($faktur['status'] ?? '') === 'swapped') {
                $actual = strtoupper($faktur['actual_type'] ?? 'dokumen lain');
                $issues[] = "- Faktur Pajak (FP): File di kolom Faktur Pajak tertukar (terdeteksi berisi {$actual}). Begitu juga dengan Faktur Pajak, mohon unggah file Faktur Pajak yang sesuai.";
            }
        }

        if (empty($issues)) {
            $issues[] = '- Dokumen pengajuan program tidak sesuai. Mohon sesuaikan nama program dengan isi CN, begitu juga dengan Agreement & Faktur Pajak.';
        }

        $lines = [
            $salesGreeting,
            '',
            'Mohon bantuannya untuk perbaikan dokumen pada pengajuan klaim program berikut:',
            '',
            'Data Pengajuan:',
            "- Dealer: {$dealerName} ({$idReal})",
            "- Program: {$programName}",
            "- Region: {$region}",
            '',
            'Catatan Dokumen:',
            implode("\n", $issues),
            '',
            'Ketentuan Upload Dokumen:',
            '- File dokumen wajib dipisah satu per satu sesuai kolomnya (jangan digabung menjadi satu file atau ditumpuk dalam satu kolom).',
            '- Kolom CN diisi khusus untuk file Credit Note.',
            '- Kolom Agr diisi khusus untuk file Agreement.',
            '- Kolom Faktur Pajak diisi khusus untuk file Faktur Pajak.',
            '- Mohon sesuaikan nama program dengan isi CN, begitu juga dengan Agreement & Faktur Pajak.',
            '',
            'Mohon upload dokumen ulang yang sesuai agar proses verifikasi klaim dapat segera diproses.',
            '',
            'Terima kasih atas kerja samanya.',
        ];

        return implode("\n", $lines);
    }
}

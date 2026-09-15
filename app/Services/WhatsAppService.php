<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\WhatsAppLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppService
{
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
        $messageText = mb_substr($this->buildInvoiceMessage($invoice), 0, 950);

        $pdfUrl = $this->resolvePublicPdfUrl($invoice);
        $filename = "Invoice_{$invoice->invoice_number}.pdf";

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
            $client = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Idempotency-Key' => $uuid,
            ])->timeout(20);

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
    public function buildInvoiceMessage(Invoice $invoice): string
    {
        $recipientName = $invoice->customer_name ?: $invoice->dealer_name;
        $dppFormatted = number_format((float) $invoice->dpp, 0, '.', ',');
        $netpayFormatted = number_format((float) $invoice->netpay, 0, '.', ',');
        $baseUrl = rtrim(config('services.wag.public_url', config('app.url')), '/');
        $pdfUrl = $baseUrl."/invoices/{$invoice->id}/pdf";

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
        $lines[] = '3. Dokumen Realme upload ke: https://bit.ly/ProgramRealmeJabar';
        $lines[] = '';
        $lines[] = 'Terima kasih atas kerja sama Anda.';
        $lines[] = '';
        $lines[] = '_Ocean Space - Pesan otomatis sistem SCM Invoice_';

        return implode("\n", $lines);
    }

    /**
     * Resolve publicly accessible URL for the invoice PDF document.
     */
    public function resolvePublicPdfUrl(Invoice $invoice): string
    {
        // 1. If explicit public base URL is configured (e.g. production domain, tunnel), use it
        $customBase = config('services.wag.public_url');
        if (! empty($customBase) && ! str_contains($customBase, 'localhost') && ! str_contains($customBase, '127.0.0.1')) {
            return rtrim($customBase, '/')."/invoices/{$invoice->id}/pdf";
        }

        // 2. If APP_URL is already public (not localhost)
        $appUrl = config('app.url');
        if (! empty($appUrl) && ! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '127.0.0.1')) {
            return rtrim($appUrl, '/')."/invoices/{$invoice->id}/pdf";
        }

        // 3. For local development, mirror the PDF to a temporary public host so WAGHub can fetch it
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

            if (! empty($pdfContent)) {
                $filename = "Invoice_{$invoice->invoice_number}.pdf";
                $response = Http::withoutVerifying()
                    ->timeout(15)
                    ->attach('file', $pdfContent, $filename)
                    ->post('https://tmpfiles.org/api/v1/upload');

                if ($response->successful() && $rawUrl = $response->json('data.url')) {
                    return str_replace('tmpfiles.org/', 'tmpfiles.org/dl/', $rawUrl);
                }
            }
        } catch (Exception $e) {
            Log::warning("Gagal mirror PDF invoice ke host publik: {$e->getMessage()}");
        }

        return null;
    }
}

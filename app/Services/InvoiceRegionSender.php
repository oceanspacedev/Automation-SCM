<?php

namespace App\Services;

use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\WhatsAppLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WhatsApp of generated invoices to telemarketing: the invoices of one region that share a telemarketing number
 * (from the store in Master Dealer) go together as one PDF, one invoice per page, e.g. "Invoice Region CIREBON.pdf".
 */
class InvoiceRegionSender
{
    public function __construct(
        protected WhatsAppService $whatsApp,
        protected InvoiceGenerator $generator,
    ) {}

    /**
     * Send the invoices per region & telemarketing number.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array{
     *     sent: array<int, string>,
     *     files: list<array{region: string, phone: string, count: int, success: bool, message: string}>,
     *     without_phone: list<string>
     * }
     */
    public function send(Collection $invoices): array
    {
        $groups = [];
        $withoutPhone = [];

        foreach ($invoices as $invoice) {
            $dealer = $this->dealerOf($invoice);
            $phone = $this->whatsApp->formatPhone($dealer?->telemarketing_phone);

            if (! $phone) {
                $withoutPhone[] = $invoice->invoice_number;

                continue;
            }

            $region = strtoupper(trim((string) ($invoice->draft?->region ?: $dealer->region))) ?: 'TANPA REGION';
            $groups["{$region}|{$phone}"] ??= [
                'region' => $region,
                'phone' => $phone,
                'name' => trim((string) $dealer->telemarketing) ?: null,
                'invoices' => collect(),
            ];
            $groups["{$region}|{$phone}"]['invoices']->push($invoice);
        }

        $sent = [];
        $files = [];

        foreach ($groups as $group) {
            $result = $this->sendGroup($group['region'], $group['phone'], $group['name'], $group['invoices']);

            if ($result['success']) {
                foreach ($group['invoices'] as $invoice) {
                    $sent[$invoice->id] = $group['phone'];
                }
            }

            $files[] = [
                'region' => $group['region'],
                'phone' => $group['phone'],
                'count' => $group['invoices']->count(),
                'success' => $result['success'],
                'message' => $result['message'],
            ];
        }

        return ['sent' => $sent, 'files' => $files, 'without_phone' => $withoutPhone];
    }

    /**
     * One PDF of the group's invoices to its telemarketing, recorded in each invoice's WhatsApp history.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array{success: bool, message: string, provider_id: ?string}
     */
    private function sendGroup(string $region, string $phone, ?string $name, Collection $invoices): array
    {
        $filename = "Invoice Region {$region}.pdf";

        try {
            $pdf = $this->generator->renderCombinedPdf($invoices);
            $url = $this->publicUrl($pdf, $filename, $region);
            $result = $this->whatsApp->sendDocument($phone, $this->whatsApp->buildRegionInvoiceMessage($region, $name, $invoices, $url),
                $url, $filename, 'INV-REGION-'.Str::slug($region).'-'.now()->format('YmdHis'));
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => "PDF invoice region {$region} gagal dibuat: {$e->getMessage()}", 'provider_id' => null];
        }

        foreach ($invoices as $invoice) {
            WhatsAppLog::create([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipient_phone' => $phone,
                'status' => $result['success'] ? 'sent' : 'failed',
                'provider_message_id' => $result['provider_id'] ?? null,
                'error_message' => $result['success'] ? null : $result['message'],
            ]);
        }

        return $result;
    }

    /**
     * Keep the PDF on the public disk and give a link the WhatsApp gateway can fetch: the app's own public URL, or
     * a temporary public copy while the app runs on localhost.
     */
    private function publicUrl(string $pdf, string $filename, string $region): string
    {
        $path = 'invoices/regions/'.now()->format('Ymd-His').'-'.Str::slug($region).'-'.Str::lower(Str::random(6)).'.pdf';
        Storage::disk('public')->put($path, $pdf);

        $baseUrl = $this->whatsApp->resolveBaseUrl();
        if (! str_contains($baseUrl, 'localhost') && ! str_contains($baseUrl, '127.0.0.1')) {
            return "{$baseUrl}/storage/{$path}";
        }

        return $this->whatsApp->mirrorPublicly($pdf, $filename) ?? url("storage/{$path}");
    }

    /**
     * The invoice's store in Master Dealer: by the draft's kode BT first, then by its dealer code (ID Realme).
     */
    private function dealerOf(Invoice $invoice): ?Dealer
    {
        return Dealer::findByIdReal($invoice->draft?->kode_bt, $invoice->dealer_name)
            ?? Dealer::findByIdReal($invoice->dealer_code ?: $invoice->draft?->dealer_code, $invoice->dealer_name);
    }
}

<?php

namespace App\Services;

use App\Models\Draft;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceGenerator
{
    protected InvoiceCalculator $calculator;

    public function __construct(InvoiceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Generate an Invoice from an eligible Draft.
     *
     * @throws Exception
     */
    public function generate(Draft $draft, ?string $billTo = null): Invoice
    {
        return DB::transaction(function () use ($draft, $billTo) {
            // Lock draft row for update to prevent concurrent duplicate generation
            $lockedDraft = Draft::where('id', $draft->id)->lockForUpdate()->firstOrFail();

            // 1. Check if already invoiced or duplicate invoice exists
            if ($lockedDraft->status === 'invoiced' || $lockedDraft->invoice()->exists()) {
                throw new Exception("Draft #{$lockedDraft->id} sudah pernah di-generate menjadi invoice.");
            }

            // 2. Validate invoice type (DSA / NPS FL / REGULAR)
            $invoiceType = strtoupper(trim((string) $lockedDraft->invoice_type));
            if ($invoiceType === 'REGULER') {
                $invoiceType = 'REGULAR';
            }
            if (! in_array($invoiceType, ['DSA', 'NPS FL', 'REGULAR'], true)) {
                throw new Exception("Tipe invoice tidak valid ('{$lockedDraft->invoice_type}'). Harus 'DSA', 'NPS FL', atau 'REGULAR'.");
            }

            // 3. Calculate values using system business rules
            $calc = $this->calculator->calculate($lockedDraft);

            // Prefer draft values if already populated (> 0), otherwise use calculated values
            $dpp = ((float) $lockedDraft->dpp > 0) ? (float) $lockedDraft->dpp : $calc['dpp'];
            $dppLain = ((float) $lockedDraft->dpp_lain > 0) ? (float) $lockedDraft->dpp_lain : $calc['dpp_lain'];
            $ppn = ((float) $lockedDraft->ppn > 0) ? (float) $lockedDraft->ppn : $calc['ppn'];
            $pph = ((float) $lockedDraft->pph > 0) ? (float) $lockedDraft->pph : $calc['pph'];
            $netpay = ((float) $lockedDraft->netpay > 0) ? (float) $lockedDraft->netpay : $calc['netpay'];
            $supportAmount = ((float) $lockedDraft->support_amount > 0) ? (float) $lockedDraft->support_amount : $calc['support_amount'];

            // 4. Nomor invoice = No CN dari file draft yang diupload, jadi wajib diisi & belum dipakai invoice lain
            $invoiceNumber = trim((string) $lockedDraft->cn_number);
            if ($invoiceNumber === '') {
                throw new Exception('No CN belum diisi. Nomor invoice diambil dari No CN di file draft.');
            }
            if (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
                throw new Exception("No CN {$invoiceNumber} sudah dipakai invoice lain. Periksa lagi No CN di file draft.");
            }

            // Customer lookup for Bill To information
            if (! empty($billTo) && ($selectedBillTo = CustomerLookupService::getBillTo($billTo))) {
                $customerName = $selectedBillTo['name'];
                $customerAddress = ! empty($selectedBillTo['address']) && $selectedBillTo['address'] !== '-'
                    ? $selectedBillTo['address']
                    : null;
                $customerNpwp = ! empty($selectedBillTo['npwp']) && $selectedBillTo['npwp'] !== '-'
                    ? $selectedBillTo['npwp']
                    : null;
            } else {
                $customerInfo = CustomerLookupService::lookup($lockedDraft->customer_name);
                $customerName = $customerInfo['name'] ?? $lockedDraft->customer_name;
                $customerAddress = ! empty($customerInfo['address']) && $customerInfo['address'] !== '-'
                    ? $customerInfo['address']
                    : null;
                $customerNpwp = ! empty($customerInfo['npwp']) && $customerInfo['npwp'] !== '-'
                    ? $customerInfo['npwp']
                    : null;
            }

            // 5. Create Invoice record
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'draft_id' => $lockedDraft->id,
                'invoice_type' => $invoiceType,
                'rsm' => $lockedDraft->rsm,
                'dealer_code' => $lockedDraft->dealer_code,
                'dealer_name' => $lockedDraft->dealer_name,
                'customer_name' => $customerName,
                'customer_address' => $customerAddress,
                'customer_npwp' => $customerNpwp,
                'npwp' => $lockedDraft->npwp,
                'npwp_name' => $lockedDraft->npwp_name,
                'npwp_type' => $lockedDraft->npwp_type,
                'pph_type' => $lockedDraft->pph_type,
                'support_amount' => $supportAmount,
                'dpp' => $dpp,
                'dpp_lain' => $dppLain,
                'ppn' => $ppn,
                'pph' => $pph,
                'netpay' => $netpay,
                'item_code' => $lockedDraft->item_code,
                'item_name' => $lockedDraft->item_name,
                'address' => $lockedDraft->address,
                'email' => $lockedDraft->email,
                'whatsapp' => $lockedDraft->whatsapp,
                'program_name' => $lockedDraft->program_name,
                'program_period' => $lockedDraft->program_period,
                'cn_number' => $lockedDraft->cn_number,
                'invoice_date' => $lockedDraft->invoice_date ?: date('d/m/Y'),
                'ref_note' => $lockedDraft->ref_note,
                'status' => 'generated',
            ]);

            // 6. Generate PDF file
            $pdfPath = $this->renderAndSavePdf($invoice);
            $invoice->update(['pdf_path' => $pdfPath]);

            // 7. Update draft status
            $lockedDraft->update(['status' => 'invoiced']);

            return $invoice;
        });
    }

    /**
     * Render and save PDF using DomPDF.
     *
     * @return string Relative storage path
     */
    public function renderAndSavePdf(Invoice $invoice): string
    {
        $viewName = match ($invoice->invoice_type) {
            'DSA' => 'invoices.dsa',
            'REGULAR', 'REGULER' => 'invoices.regular',
            default => 'invoices.nps-fl',
        };

        $pdf = Pdf::loadView($viewName, ['invoice' => $invoice])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        $filename = "invoices/{$invoice->invoice_number}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        return $filename;
    }
}

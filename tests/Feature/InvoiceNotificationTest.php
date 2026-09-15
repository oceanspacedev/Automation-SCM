<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Models\Draft;
use App\Models\Invoice;
use App\Services\DraftImportService;
use App\Services\InvoiceGenerator;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InvoiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_draft_with_whatsapp_column(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'No.', 'Region', 'RSM', 'Dealer Code', 'Kode BT', 'Nama Cust by CSA',
            'Dealer Name', 'Realqty', 'NPWP', 'Nama NPWP', 'Jenis NPWP', 'Jenis Pph',
            'Support Amount', 'Dpp', 'Dpp Lain', 'Ppn', 'Pph', 'Netpay',
            'KODE ITEM', 'NAMA ITEM', 'Alamat', 'Email', 'No WhatsApp', 'Nama Program', 'Periode Program',
            'No CN', 'Tanggal Inv', 'REFNOTE', 'DSA/NPS FL',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $row1 = [
            '1', 'JABO', 'RSM01', 'DLR001', 'BT01', 'Cust WA',
            'PT Dealer WA', 10, '01.234.567.8-901.000', 'PT Dealer WA', 'BADAN', 'BADAN',
            10000000, 9009009, 8258258, 990991, 180180, 9819820,
            'ITM01', 'Barang Promo', 'Jl. Merdeka No 1', 'dealer@example.com', '081234567890',
            'Program Q1', 'Jan-Mar 2026', 'CN001', '2026-03-01', 'Ref Note', 'DSA',
        ];
        $sheet->fromArray([$row1], null, 'A2');

        $tempPath = tempnam(sys_get_temp_dir(), 'import_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $uploadedFile = new UploadedFile($tempPath, 'test_draft.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        /** @var DraftImportService $importService */
        $importService = app(DraftImportService::class);
        $result = $importService->import($uploadedFile);

        $this->assertEquals(1, $result['success']);
        $this->assertDatabaseHas('drafts', [
            'dealer_code' => 'DLR001',
            'email' => 'dealer@example.com',
            'whatsapp' => '081234567890',
        ]);

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    public function test_generate_invoice_copies_whatsapp_from_draft(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR002',
            'dealer_name' => 'Toko Subur',
            'customer_name' => 'Subur Jaya',
            'support_amount' => 5000000,
            'dpp' => 4504505,
            'dpp_lain' => 0,
            'ppn' => 0,
            'pph' => 112613,
            'netpay' => 4391892,
            'pph_type' => 'PRIBADI',
            'npwp_type' => 'PRIBADI',
            'invoice_type' => 'NPS FL',
            'status' => 'ready',
            'email' => 'subur@example.com',
            'whatsapp' => '08987654321',
        ]);

        /** @var InvoiceGenerator $generator */
        $generator = app(InvoiceGenerator::class);
        $invoice = $generator->generate($draft);

        $this->assertEquals('subur@example.com', $invoice->email);
        $this->assertEquals('08987654321', $invoice->whatsapp);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'email' => 'subur@example.com',
            'whatsapp' => '08987654321',
        ]);
    }

    public function test_whatsapp_service_formats_phone_numbers(): void
    {
        /** @var WhatsAppService $service */
        $service = app(WhatsAppService::class);

        $this->assertEquals('628123456789', $service->formatPhone('08123456789'));
        $this->assertEquals('628123456789', $service->formatPhone('8123456789'));
        $this->assertEquals('628123456789', $service->formatPhone('+628123456789'));
        $this->assertEquals('628123456789', $service->formatPhone('0812-3456-789'));
        $this->assertEquals('628123456789', $service->formatPhone('628123456789'));
        $this->assertNull($service->formatPhone('123')); // too short
        $this->assertNull($service->formatPhone(null));
    }

    public function test_send_invoice_notification_sends_email_and_whatsapp(): void
    {
        Mail::fake();
        config(['services.wag.public_url' => 'https://cdn.example.com']);
        Http::fake([
            '*/api/v1/messages' => Http::response([
                'data' => [
                    'id' => 'waghub-msg-123',
                    'provider_message_id' => 'prov-456',
                    'status' => 'provider_accepted',
                ],
            ], 201),
        ]);

        $draft = Draft::create([
            'dealer_code' => 'DLR003',
            'dealer_name' => 'Dealer Sukses',
            'customer_name' => 'Sukses Abadi',
            'support_amount' => 1000000,
            'dpp' => 900901,
            'dpp_lain' => 0,
            'ppn' => 0,
            'pph' => 0,
            'netpay' => 900901,
            'invoice_type' => 'DSA',
            'status' => 'invoiced',
            'email' => 'sukses@example.com',
            'whatsapp' => '081234567890',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-TEST01',
            'draft_id' => $draft->id,
            'invoice_type' => 'DSA',
            'dealer_code' => 'DLR003',
            'dealer_name' => 'Dealer Sukses',
            'customer_name' => 'Sukses Abadi',
            'support_amount' => 1000000,
            'dpp' => 900901,
            'dpp_lain' => 0,
            'ppn' => 0,
            'pph' => 0,
            'netpay' => 900901,
            'email' => 'sukses@example.com',
            'whatsapp' => '081234567890',
            'status' => 'generated',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/send-email", [
            'email' => 'sukses@example.com',
            'whatsapp' => '081234567890',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        // Verify Email Sent
        Mail::assertSent(InvoiceMail::class, function ($mail) {
            return $mail->hasTo('sukses@example.com');
        });

        // Verify HTTP WhatsApp Sent
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/messages') &&
                $request['recipient']['value'] === '6281234567890' &&
                $request['message']['type'] === 'document' &&
                ! empty($request['message']['attachment']['url']) &&
                $request['message']['attachment']['mime_type'] === 'application/pdf';
        });

        // Verify Database Updates
        $invoice->refresh();
        $this->assertEquals('sent', $invoice->status);
        $this->assertNotNull($invoice->email_sent_at);
        $this->assertNotNull($invoice->whatsapp_sent_at);

        // Verify Logs
        $this->assertDatabaseHas('email_logs', [
            'invoice_id' => $invoice->id,
            'recipient_email' => 'sukses@example.com',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('whatsapp_logs', [
            'invoice_id' => $invoice->id,
            'recipient_phone' => '6281234567890',
            'status' => 'sent',
            'provider_message_id' => 'prov-456',
        ]);
    }
}

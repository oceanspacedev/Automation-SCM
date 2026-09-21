<?php

namespace Tests\Feature;

use App\Models\Draft;
use App\Models\Invoice;
use App\Services\InvoiceGenerator;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(InvoiceGenerator::class);
    }

    public function test_can_generate_dsa_invoice_from_draft(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR001',
            'dealer_name' => 'PT Mitra Sejahtera',
            'customer_name' => 'John Doe',
            'support_amount' => 10000000,
            'pph_type' => 'BADAN',
            'npwp_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        $invoice = $this->generator->generate($draft);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('DSA', $invoice->invoice_type);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertEquals(9009009, $invoice->dpp);
        $this->assertEquals(8258258, $invoice->dpp_lain);
        $this->assertEquals(990991, $invoice->ppn);
        $this->assertEquals(180180, $invoice->pph);
        $this->assertEquals(9819820, $invoice->netpay);

        // Verify draft updated to invoiced
        $this->assertEquals('invoiced', $draft->fresh()->status);
    }

    public function test_can_generate_nps_fl_invoice_from_draft(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR002',
            'dealer_name' => 'CV Maju Lancar',
            'support_amount' => 5000000,
            'pph_type' => 'PRIBADI',
            'npwp_type' => 'PRIBADI',
            'invoice_type' => 'NPS FL',
            'status' => 'ready',
        ]);

        $invoice = $this->generator->generate($draft);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('NPS FL', $invoice->invoice_type);
        $this->assertEquals('invoiced', $draft->fresh()->status);
    }

    public function test_cannot_generate_duplicate_invoice_from_same_draft(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR003',
            'dealer_name' => 'PT Makmur Sentosa',
            'support_amount' => 10000000,
            'pph_type' => 'BADAN',
            'npwp_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        // First generation succeeds
        $this->generator->generate($draft);

        // Second generation must throw Exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah pernah di-generate');

        $this->generator->generate($draft->fresh());
    }

    public function test_invalid_invoice_type_fails(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR004',
            'support_amount' => 1000000,
            'pph_type' => 'BADAN',
            'invoice_type' => 'INVALID_TYPE',
            'status' => 'ready',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Tipe invoice tidak valid');

        $this->generator->generate($draft);
    }

    public function test_invoice_preview_route(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR005',
            'dealer_name' => 'PT Sukses Mandiri',
            'support_amount' => 10000000,
            'pph_type' => 'BADAN',
            'npwp_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        $invoice = $this->generator->generate($draft);

        $response = $this->get("/invoices/{$invoice->id}/preview");
        $response->assertStatus(200);
        $response->assertSee('INVOICE');
        $response->assertSee('Nama DSA');
        $response->assertSee($invoice->invoice_number);
    }

    public function test_can_use_cn_number_as_invoice_number(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLCB0369',
            'dealer_name' => 'MAJU HARDWARE',
            'customer_name' => 'CV TOP SELULAR',
            'support_amount' => 835701,
            'dpp' => 835701,
            'dpp_lain' => 766059,
            'ppn' => 91927,
            'pph' => 16714,
            'netpay' => 910914,
            'item_code' => '38000016',
            'item_name' => 'MARKETING SERVICE FEE',
            'cn_number' => '2608000043',
            'invoice_date' => '9/10/2026',
            'invoice_type' => 'NPS FL',
            'rsm' => '0',
            'status' => 'ready',
        ]);

        $invoice = $this->generator->generate($draft);

        $this->assertEquals('2608000043', $invoice->invoice_number);
        $this->assertEquals('CV TOP SELULAR', $invoice->customer_name);
        $this->assertStringContainsString('Pekalipan', $invoice->customer_address);
        $this->assertEquals('31.352.339.1-426.000', $invoice->customer_npwp);
    }

    public function test_can_bulk_generate_invoices_for_all_ready_drafts(): void
    {
        Draft::create([
            'dealer_code' => 'DLR010',
            'dealer_name' => 'Dealer A',
            'support_amount' => 1000000,
            'pph_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        Draft::create([
            'dealer_code' => 'DLR011',
            'dealer_name' => 'Dealer B',
            'support_amount' => 2000000,
            'pph_type' => 'PRIBADI',
            'invoice_type' => 'NPS FL',
            'status' => 'ready',
        ]);

        Draft::create([
            'dealer_code' => 'DLR012',
            'dealer_name' => 'Dealer C',
            'support_amount' => 3000000,
            'invoice_type' => 'NPS FL',
            'status' => 'error',
        ]);

        $response = $this->postJson('/api/invoices/generate-all');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'generated_count' => 2,
        ]);

        $this->assertEquals(2, Invoice::count());
        $this->assertEquals(2, Draft::where('status', 'invoiced')->count());
        $this->assertEquals(1, Draft::where('status', 'error')->count());
    }

    public function test_can_bulk_generate_invoices_for_selected_drafts_only(): void
    {
        $draft1 = Draft::create([
            'dealer_code' => 'DLR020',
            'dealer_name' => 'Dealer Selected 1',
            'support_amount' => 1000000,
            'pph_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        $draft2 = Draft::create([
            'dealer_code' => 'DLR021',
            'dealer_name' => 'Dealer Selected 2',
            'support_amount' => 2000000,
            'pph_type' => 'PRIBADI',
            'invoice_type' => 'NPS FL',
            'status' => 'ready',
        ]);

        $draft3 = Draft::create([
            'dealer_code' => 'DLR022',
            'dealer_name' => 'Dealer Not Selected',
            'support_amount' => 1500000,
            'pph_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        // Only generate for draft1 and draft2
        $response = $this->postJson('/api/invoices/generate-batch', [
            'ids' => [$draft1->id, $draft2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'generated_count' => 2,
        ]);

        $this->assertEquals('invoiced', $draft1->fresh()->status);
        $this->assertEquals('invoiced', $draft2->fresh()->status);
        // draft3 must remain ready
        $this->assertEquals('ready', $draft3->fresh()->status);
        $this->assertEquals(2, Invoice::count());
    }

    public function test_can_generate_invoice_with_cv_top_bill_to(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR030',
            'dealer_name' => 'Toko Pelanggan',
            'customer_name' => 'CV OKEY MEGAH PERKASA', // Originally customer is toko
            'support_amount' => 5000000,
            'pph_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        $invoice = $this->generator->generate($draft, 'CV TOP');

        $this->assertEquals('CV TOP SELULAR', $invoice->customer_name);
        $this->assertStringContainsString('Pekalipan', $invoice->customer_address);
        $this->assertEquals('31.352.339.1-426.000', $invoice->customer_npwp);
    }

    public function test_can_generate_invoice_with_pt_rism_bill_to(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR031',
            'dealer_name' => 'Toko Pelanggan 2',
            'customer_name' => 'CV OKEY MEGAH PERKASA',
            'support_amount' => 5000000,
            'pph_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        $invoice = $this->generator->generate($draft, 'PT RISM');

        $this->assertEquals('PT RETAIL INDONESIA SELALU MAJU', $invoice->customer_name);
        $this->assertStringContainsString('MANGGA DUA SQUARE', $invoice->customer_address);
        $this->assertEquals('61.186.183.2-044.000', $invoice->customer_npwp);
    }

    public function test_can_generate_invoice_with_pt_msi_bill_to(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR032',
            'dealer_name' => 'Toko Pelanggan 3',
            'customer_name' => 'CV OKEY MEGAH PERKASA',
            'support_amount' => 5000000,
            'pph_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        $invoice = $this->generator->generate($draft, 'PT MSI');

        $this->assertEquals('PT MEDIA SELULER INDONESIA', $invoice->customer_name);
        $this->assertStringContainsString('Telkom Landmark Tower', $invoice->customer_address);
        $this->assertEquals('01.555.666.7-011.000', $invoice->customer_npwp);
    }

    public function test_api_generate_single_with_bill_to(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR033',
            'dealer_name' => 'Toko Pelanggan 4',
            'customer_name' => 'Customer Biasa',
            'support_amount' => 3000000,
            'pph_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        $response = $this->postJson("/api/invoices/generate/{$draft->id}", [
            'bill_to' => 'PT RISM',
        ]);

        $response->assertStatus(200);
        $invoice = Invoice::where('draft_id', $draft->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('PT RETAIL INDONESIA SELALU MAJU', $invoice->customer_name);
    }

    public function test_api_generate_all_with_bill_to(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR034',
            'dealer_name' => 'Toko Pelanggan 5',
            'customer_name' => 'Customer Biasa',
            'support_amount' => 3000000,
            'pph_type' => 'BADAN',
            'invoice_type' => 'DSA',
            'status' => 'ready',
        ]);

        $response = $this->postJson('/api/invoices/generate-all', [
            'ids' => [$draft->id],
            'bill_to' => 'CV TOP',
        ]);

        $response->assertStatus(200);
        $invoice = Invoice::where('draft_id', $draft->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('CV TOP SELULAR', $invoice->customer_name);
    }

    public function test_bill_to_options_endpoint(): void
    {
        $response = $this->getJson('/api/bill-to-options');

        $response->assertStatus(200);
        $response->assertJsonFragment(['code' => 'CV TOP']);
        $response->assertJsonFragment(['code' => 'PT RISM']);
        $response->assertJsonFragment(['code' => 'PT MSI']);
    }

    public function test_can_generate_regular_invoice_from_draft(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR035',
            'dealer_name' => 'CV Sumber Rezeki',
            'customer_name' => 'Pelanggan Regular',
            'support_amount' => 6000000,
            'pph_type' => 'BADAN',
            'npwp_type' => 'BADAN',
            'invoice_type' => 'REGULAR',
            'status' => 'ready',
        ]);

        $invoice = $this->generator->generate($draft);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('REGULAR', $invoice->invoice_type);
        $this->assertEquals('invoiced', $draft->fresh()->status);
    }

    public function test_regular_invoice_preview_does_not_display_nama_dsa_or_nama_nps_fl(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR036',
            'dealer_name' => 'CV Sejahtera Abadi',
            'customer_name' => 'Pelanggan Regular 2',
            'support_amount' => 4500000,
            'pph_type' => 'BADAN',
            'npwp_type' => 'BADAN',
            'invoice_type' => 'REGULAR',
            'status' => 'ready',
        ]);

        $invoice = $this->generator->generate($draft);

        $response = $this->get("/invoices/{$invoice->id}/preview");
        $response->assertStatus(200);
        $response->assertSee('INVOICE');
        $response->assertSee('Nomor Invoice');
        $response->assertSee('Tanggal Invoice');
        $response->assertDontSee('Nama DSA');
        $response->assertDontSee('Nama NPS FL');
        $response->assertSee($invoice->invoice_number);
    }
}

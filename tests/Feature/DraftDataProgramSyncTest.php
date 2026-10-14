<?php

namespace Tests\Feature;

use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\Draft;
use App\Models\MasterProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DraftDataProgramSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_program_code_is_resolved_from_master_program_and_sent_to_data_program(): void
    {
        Http::fake();

        MasterProgram::create([
            'code' => 'PROGRAM 345',
            'name' => 'PROGRAM DSA SEPTEMBER 2026',
            'period' => 'Periode 1-30 September 2026',
        ]);

        $draftId = $this->postJson('/api/drafts', [
            'cn_number' => 'CN-DP-001',
            'dealer_name' => 'SUMBER JAYA CELL',
            'support_amount' => 1000000,
            'program_name' => 'PROGRAM DSA SEPTEMBER 2026',
            'invoice_type' => 'DSA',
        ])->assertCreated()->assertJsonPath('data.program_code', 'PROGRAM 345')->json('data.id');

        $this->postJson("/api/invoices/generate/{$draftId}")->assertOk();

        $this->assertDatabaseHas('data_programs', [
            'draft_id' => $draftId,
            'program' => 'PROGRAM 345',
            'program_name' => 'PROGRAM DSA SEPTEMBER 2026',
        ]);
    }

    public function test_sales_and_telemarketing_from_master_dealer_are_filled_in_data_program(): void
    {
        Http::fake();

        MasterProgram::create(['code' => 'PROGRAM 351', 'name' => 'PROGRAM DSA AGUSTUS 2026 SALES TEST']);
        Dealer::create([
            'area' => 'jabar',
            'kode_bt' => 'BT04051',
            'dealer_name' => 'CV MAJU BERSAMA',
            'sales_person' => 'ANDI',
            'telemarketing' => 'SITI',
            'telemarketing_phone' => '081234567890',
        ]);

        $draftId = $this->postJson('/api/drafts', [
            'cn_number' => 'CN-DP-002',
            'dealer_name' => 'CV MAJU BERSAMA',
            'kode_bt' => 'BT04051',
            'support_amount' => 1000000,
            'program_name' => 'PROGRAM DSA AGUSTUS 2026 SALES TEST',
            'invoice_type' => 'DSA',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/invoices/generate/{$draftId}")->assertOk();

        $this->assertDatabaseHas('data_programs', [
            'draft_id' => $draftId,
            'program' => 'PROGRAM 351',
            'sales_person' => 'ANDI',
            'telemarketing' => 'SITI',
            'telemarketing_phone' => '081234567890',
        ]);
    }

    public function test_existing_draft_without_program_code_gets_code_from_master_program_on_sync(): void
    {
        Http::fake();

        MasterProgram::create([
            'code' => 'PROGRAM 342',
            'name' => 'PROGRAM DSA AGUSTUS 2026 TEST',
            'period' => 'Periode 1-31 Agustus 2026',
        ]);

        $draft = Draft::create([
            'cn_number' => 'CN-DP-003',
            'dealer_name' => 'GLOBAL CELL KUDUS',
            'support_amount' => 1000000,
            'program_name' => 'PROGRAM DSA AGUSTUS 2026 TEST',
            'invoice_type' => 'REGULAR',
            'status' => 'invoiced',
        ]);

        $this->postJson("/api/drafts/{$draft->id}/push-to-data-program")->assertOk();

        $this->assertDatabaseHas('data_programs', [
            'draft_id' => $draft->id,
            'program' => 'PROGRAM 342',
            'periode' => 'Periode 1-31 Agustus 2026',
        ]);
        $this->assertEquals('PROGRAM 342', $draft->fresh()->program_code);
    }

    public function test_draft_is_synced_to_data_program_only_after_invoice_is_generated(): void
    {
        Http::fake();

        $payload = [
            'dealer_name' => 'PT ABADI ELEKTRONIK',
            'dealer_code' => 'DLR0099',
            'customer_name' => 'CUST001',
            'region' => 'JABODETABEK',
            'kode_bt' => 'BT8899',
            'support_amount' => 10000000,
            'program_name' => 'PROGRAM CASHBACK MARET',
            'program_period' => 'Maret 2026',
            'cn_number' => 'CN-2026-001',
            'invoice_date' => '2026-03-15',
            'invoice_type' => 'NPS FL',
            'pph_type' => 'BADAN',
            'npwp_type' => 'BADAN',
            'npwp' => '01.234.567.8-901.000',
            'npwp_name' => 'PT ABADI ELEKTRONIK',
            'ref_note' => 'Catatan khusus draft',
        ];

        $response = $this->postJson('/api/drafts', $payload);

        $response->assertStatus(201);
        $draftId = $response->json('data.id');
        $this->assertNotNull($draftId);
        $this->assertDatabaseMissing('data_programs', ['draft_id' => $draftId]);

        $this->postJson("/api/invoices/generate/{$draftId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        // Verify DataProgram record created
        $dp = DataProgram::where('draft_id', $draftId)->first();
        $this->assertNotNull($dp);
        $this->assertEquals('draft_'.$draftId, $dp->row_hash);
        $this->assertEquals('PT ABADI ELEKTRONIK', $dp->dealer_name);
        $this->assertNull($dp->program); // Tidak ada di Master Program, jadi kode program dikosongkan (bukan tipe invoice)
        $this->assertEquals('BT8899', $dp->kode_bt);
        $this->assertEquals('PROGRAM CASHBACK MARET', $dp->program_name);
        $this->assertEquals('Maret 2026', $dp->periode);
        $this->assertEquals('JABODETABEK', $dp->region);
        $this->assertEquals('JABODETABEK', $dp->big_region);
        $this->assertEquals('DLR0099', $dp->id_gs);
        $this->assertNull($dp->kode_supplier); // kode_supplier tidak diisi dari draft karena sudah ada dealer_name
        $this->assertEquals('BADAN', $dp->wajib_pajak);
        $this->assertEquals('BADAN', $dp->trf_pph);
        $this->assertEquals(10000000, $dp->incentive);
        $this->assertEquals('2026-03-15', $dp->tgl_input);
        $this->assertEquals('Catatan khusus draft', $dp->noted);
        $this->assertContains($dp->keterangan, ['LEBIH DARI 30 HARI', 'KURANG DARI 30 HARI']);
        $this->assertNull($dp->cn); // CN di Data Program berasal dari matching Form Program
        $this->assertEquals('BELUM MATCH TEST', $dp->cek_dokumen);
        $this->assertEquals('BELUM BISA POTONG', $dp->status_potong_purchase);
        $this->assertEquals('BELUM POTONG', $dp->status_potong_ar);

        // Columns not in draft remain null
        $this->assertNull($dp->no_po);
        $this->assertNull($dp->sales_person);
        $this->assertNull($dp->no_sj);
        $this->assertNull($dp->agrement);

        // Relation on Draft works
        $draft = Draft::find($draftId);
        $this->assertNotNull($draft->dataProgram);
        $this->assertEquals($dp->id, $draft->dataProgram->id);
    }

    public function test_single_draft_can_be_pushed_to_data_program(): void
    {
        Http::fake();

        $draft = Draft::create([
            'cn_number' => 'CN-DP-004',
            'no' => '1',
            'dealer_name' => 'SUMBER JAYA CELL',
            'dealer_code' => 'DLR055',
            'region' => 'JAWA TENGAH',
            'kode_bt' => 'BT7766',
            'program_name' => 'PROGRAM TARGET Q1',
            'program_period' => 'Jan-Mar 2026',
            'support_amount' => 5000000,
            'dpp' => 4504505,
            'dpp_lain' => 4129129,
            'ppn' => 495495,
            'pph' => 90090,
            'netpay' => 4909910,
            'status' => 'invoiced',
        ]);

        $response = $this->postJson("/api/drafts/{$draft->id}/push-to-data-program");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('data_programs', [
            'draft_id' => $draft->id,
            'dealer_name' => 'SUMBER JAYA CELL',
            'kode_bt' => 'BT7766',
            'program_name' => 'PROGRAM TARGET Q1',
        ]);
    }

    public function test_batch_drafts_can_be_pushed_to_data_program(): void
    {
        Http::fake();

        $d1 = Draft::create([
            'cn_number' => 'CN-DP-005',
            'dealer_name' => 'DEALER A',
            'dealer_code' => 'D01',
            'support_amount' => 1000000,
            'program_name' => 'PROGRAM A',
            'status' => 'invoiced',
        ]);
        $d2 = Draft::create([
            'cn_number' => 'CN-DP-006',
            'dealer_name' => 'DEALER B',
            'dealer_code' => 'D02',
            'support_amount' => 2000000,
            'program_name' => 'PROGRAM B',
            'status' => 'invoiced',
        ]);

        $response = $this->postJson('/api/drafts/push-batch-to-data-program', [
            'ids' => [$d1->id, $d2->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 2);

        $this->assertDatabaseHas('data_programs', ['draft_id' => $d1->id, 'dealer_name' => 'DEALER A']);
        $this->assertDatabaseHas('data_programs', ['draft_id' => $d2->id, 'dealer_name' => 'DEALER B']);
    }

    public function test_push_to_data_program_is_idempotent_and_updates_existing_record(): void
    {
        Http::fake();

        $draft = Draft::create([
            'cn_number' => 'CN-DP-007',
            'dealer_name' => 'TOKO MAKMUR',
            'dealer_code' => 'DLR099',
            'support_amount' => 3000000,
            'program_name' => 'PROGRAM AWAL',
            'status' => 'invoiced',
        ]);

        // Push first time
        $this->postJson("/api/drafts/{$draft->id}/push-to-data-program")->assertStatus(200);
        $this->assertEquals(1, DataProgram::where('draft_id', $draft->id)->count());

        // Update draft details
        $draft->update([
            'program_name' => 'PROGRAM REVISI',
            'support_amount' => 3500000,
        ]);

        // Push second time
        $this->postJson("/api/drafts/{$draft->id}/push-to-data-program")->assertStatus(200);

        // Assert record updated without creating duplicates
        $this->assertEquals(1, DataProgram::where('draft_id', $draft->id)->count());
        $freshDp = DataProgram::where('draft_id', $draft->id)->first();
        $this->assertEquals('PROGRAM REVISI', $freshDp->program_name);
        $this->assertEquals(3500000, $freshDp->incentive);
    }

    public function test_batch_invoice_generation_auto_syncs_drafts_to_data_program(): void
    {
        Http::fake();

        $d1 = Draft::create([
            'cn_number' => 'CN-DP-008',
            'dealer_name' => 'DEALER A',
            'dealer_code' => 'D01',
            'support_amount' => 1000000,
            'program_name' => 'PROGRAM A',
            'invoice_type' => 'NPS FL',
            'status' => 'ready',
        ]);
        $d2 = Draft::create([
            'cn_number' => 'CN-DP-009',
            'dealer_name' => 'DEALER B',
            'dealer_code' => 'D02',
            'support_amount' => 2000000,
            'program_name' => 'PROGRAM B',
            'invoice_type' => 'NPS FL',
            'status' => 'ready',
        ]);

        $this->postJson('/api/invoices/generate-all', ['ids' => [$d1->id, $d2->id]])
            ->assertOk()
            ->assertJsonPath('generated_count', 2);

        $this->assertDatabaseHas('data_programs', ['draft_id' => $d1->id, 'dealer_name' => 'DEALER A']);
        $this->assertDatabaseHas('data_programs', ['draft_id' => $d2->id, 'dealer_name' => 'DEALER B']);
    }

    public function test_draft_without_invoice_cannot_be_pushed_to_data_program(): void
    {
        Http::fake();

        $draft = Draft::create([
            'cn_number' => 'CN-DP-010',
            'dealer_name' => 'TOKO BELUM INVOICE',
            'dealer_code' => 'DLR100',
            'support_amount' => 1000000,
            'program_name' => 'PROGRAM X',
            'status' => 'ready',
        ]);

        $this->postJson("/api/drafts/{$draft->id}/push-to-data-program")->assertStatus(422);
        $this->postJson('/api/drafts/push-batch-to-data-program', ['ids' => [$draft->id]])->assertStatus(422);

        $this->assertDatabaseMissing('data_programs', ['draft_id' => $draft->id]);
    }
}

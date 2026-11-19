<?php

namespace Tests\Feature;

use App\Area;
use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use App\Models\User;
use App\Services\ProgramReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramNameReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_program_match_strictly_rejects_different_months(): void
    {
        $service = app(ProgramReconciliationService::class);

        // Same core program, different months -> strictly FALSE
        $this->assertFalse(
            $service->isProgramMatch('PROGRAM DSA AGUSTUS 2026', 'PROGRAM DSA JUNI 2026')
        );
        $this->assertFalse(
            $service->isProgramMatch('PROGRAM DSA JUNI 2026', 'PROGRAM DSA AGUSTUS 2026')
        );
        $this->assertFalse(
            $service->isProgramMatch('DSA OKTOBER 2026', 'DSA NOVEMBER 2026')
        );

        // Same core program and same month -> TRUE
        $this->assertTrue(
            $service->isProgramMatch('PROGRAM DSA JUNI 2026', 'PROGRAM DSA JUNI 2026')
        );
        $this->assertTrue(
            $service->isProgramMatch('DSA JUNI 2026', 'PROGRAM DSA JUNI 2026')
        );
        $this->assertTrue(
            $service->isProgramMatch('PROGRAM DSA JUN 2026', 'PROGRAM DSA JUNI 2026')
        );
    }

    public function test_is_program_match_checks_dp_periode_and_program_column(): void
    {
        $service = app(ProgramReconciliationService::class);

        // Month in periode column conflicts with submission month
        $this->assertFalse(
            $service->isProgramMatch(
                subProg: 'PROGRAM DSA AGUSTUS 2026',
                dpProgName: 'PROGRAM DSA',
                dpPeriode: 'Periode 1-30 Juni 2026'
            )
        );

        // Month in periode matches submission month
        $this->assertTrue(
            $service->isProgramMatch(
                subProg: 'PROGRAM DSA JUNI 2026',
                dpProgName: 'PROGRAM DSA',
                dpPeriode: 'Periode 1-30 Juni 2026'
            )
        );

        // dpProgram fallback (e.g. PROGRAM 351)
        $this->assertTrue(
            $service->isProgramMatch(
                subProg: 'PROGRAM 351 OKTOBER 2026',
                dpProgName: 'PROGRAM DSA OKTOBER 2026',
                dpPeriode: 'Periode 1-31 Oktober 2026',
                dpProgram: 'PROGRAM 351'
            )
        );
    }

    public function test_is_program_match_rejects_different_years_and_core_tokens(): void
    {
        $service = app(ProgramReconciliationService::class);

        // Different years
        $this->assertFalse(
            $service->isProgramMatch('PROGRAM DSA JUNI 2025', 'PROGRAM DSA JUNI 2026')
        );

        // Different core program tokens
        $this->assertFalse(
            $service->isProgramMatch('PROGRAM CASHBACK JUNI 2026', 'PROGRAM DSA JUNI 2026')
        );
        $this->assertFalse(
            $service->isProgramMatch('PROGRAM PRICE PROTECTION JUNI 2026', 'PROGRAM DSA JUNI 2026')
        );
    }

    public function test_evaluate_match_score_returns_null_when_program_name_month_differs(): void
    {
        $service = app(ProgramReconciliationService::class);

        $dp = DataProgram::create([
            'brand' => 'realme',
            'area' => Area::Jabar->value,
            'region' => 'BIG KCPS',
            'dealer_name' => 'SM CELL',
            'kode_bt' => 'BT03743',
            'program_name' => 'PROGRAM DSA JUNI 2026',
            'net_pay' => 219594,
            'wajib_pajak' => 'PRIBADI',
            'row_hash' => 'hash_test_eval_1',
        ]);

        $sub = ProgramSubmission::create([
            'brand' => 'realme',
            'area' => Area::Jabar->value,
            'region' => 'BIG KCPS',
            'dealer_name' => 'SM CELL',
            'id_real' => 'DLFE8601',
            'program_name' => 'PROGRAM DSA AGUSTUS 2026',
            'net_pay' => 219594,
            'credit_note_url' => 'https://drive.google.com/file/d/test_cn',
            'agreement_url' => 'https://drive.google.com/file/d/test_agr',
            'row_hash' => 'hash_test_eval_sub_1',
        ]);

        $score = $service->evaluateMatchScore($dp, $sub);
        $this->assertNull($score, 'Submission with different month (AGUSTUS vs JUNI) must score null and be disqualified.');
    }

    public function test_reconcile_row_api_cannot_match_or_cut_when_program_differs(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $dp = DataProgram::create([
            'brand' => 'realme',
            'area' => Area::Jabar->value,
            'region' => 'BIG KCPS',
            'dealer_name' => 'SM CELL',
            'kode_bt' => 'BT03743',
            'program_name' => 'PROGRAM DSA JUNI 2026',
            'net_pay' => 219594,
            'status_potong_purchase' => 'BELUM BISA POTONG',
            'cek_dokumen' => 'BELUM MATCH TEST',
            'wajib_pajak' => 'PRIBADI',
            'row_hash' => 'hash_api_diff_prog_dp',
        ]);

        $sub = ProgramSubmission::create([
            'brand' => 'realme',
            'area' => Area::Jabar->value,
            'region' => 'BIG KCPS',
            'dealer_name' => 'SM CELL',
            'id_real' => 'BT03743',
            'program_name' => 'PROGRAM DSA AGUSTUS 2026',
            'net_pay' => 219594,
            'credit_note_url' => 'https://drive.google.com/file/d/test_cn_aug',
            'agreement_url' => 'https://drive.google.com/file/d/test_agr_aug',
            'row_hash' => 'hash_api_diff_prog_sub',
        ]);

        $response = $this->actingAs($user)->postJson("/api/data-program/{$dp->id}/reconcile", [
            'force' => false,
            'push_to_sheet' => false,
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('matched'));
        $this->assertNull($response->json('details.submission_id'));
        $this->assertSame('BELUM BISA POTONG', $response->json('details.status_potong_purchase'));

        $dp->refresh();
        $this->assertSame('BELUM BISA POTONG', $dp->status_potong_purchase);
        $this->assertNull($dp->cn);
        $this->assertNull($dp->agrement);
    }

    public function test_reconcile_row_resets_status_when_forced_on_previously_mismatched_row(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $sub = ProgramSubmission::create([
            'brand' => 'realme',
            'area' => Area::Jabar->value,
            'region' => 'BIG KCPS',
            'dealer_name' => 'SM CELL',
            'id_real' => 'BT03743',
            'program_name' => 'PROGRAM DSA AGUSTUS 2026',
            'net_pay' => 219594,
            'credit_note_url' => 'https://drive.google.com/file/d/test_cn_aug2',
            'agreement_url' => 'https://drive.google.com/file/d/test_agr_aug2',
            'row_hash' => 'hash_api_diff_prog_sub_2',
        ]);

        // Row that was previously erroneously marked as SUDAH POTONG from old match
        $dp = DataProgram::create([
            'brand' => 'realme',
            'area' => Area::Jabar->value,
            'region' => 'BIG KCPS',
            'dealer_name' => 'SM CELL',
            'kode_bt' => 'BT03743',
            'program_name' => 'PROGRAM DSA JUNI 2026',
            'net_pay' => 219594,
            'status_potong_purchase' => 'SUDAH POTONG',
            'cek_dokumen' => 'LENGKAP',
            'cn' => 'https://drive.google.com/file/d/test_cn_aug2',
            'agrement' => 'https://drive.google.com/file/d/test_agr_aug2',
            'match_note' => "MATCH Test Program #{$sub->id} (Finansial Sesuai: Net Pay Rp 219.594)",
            'wajib_pajak' => 'PRIBADI',
            'row_hash' => 'hash_api_diff_prog_dp_2',
        ]);

        $response = $this->actingAs($user)->postJson("/api/data-program/{$dp->id}/reconcile", [
            'force' => true,
            'push_to_sheet' => false,
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('matched'));

        $dp->refresh();
        $this->assertSame('BELUM BISA POTONG', $dp->status_potong_purchase);
        $this->assertSame('BELUM MATCH TEST', $dp->cek_dokumen);
        $this->assertNull($dp->cn);
        $this->assertNull($dp->agrement);
    }
}

<?php

namespace Tests\Feature;

use App\Models\DataProgram;
use App\Models\ProgramReconciliationLog;
use App\Models\ProgramSubmission;
use App\Services\AutoMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoMatchTest extends TestCase
{
    use RefreshDatabase;

    private const WAG_URL = 'https://waghub.mekayastudio.com/api/v1/messages';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wag.token' => 'fake_token',
            'services.wag.url' => 'https://waghub.mekayastudio.com',
            'services.auto_match.send_whatsapp' => true,
        ]);

        Http::fake([self::WAG_URL => Http::response(['success' => true, 'data' => ['id' => 'msg_1']], 200)]);
    }

    public function test_matching_cron_matches_a_new_submission_without_whatsapp(): void
    {
        $dataProgram = $this->dataProgram();
        $this->submission();

        $this->artisan('program:auto-match')->assertSuccessful();

        $dataProgram->refresh();
        $this->assertSame('BISA DI POTONG', $dataProgram->status_potong_purchase);
        $this->assertNull($dataProgram->telemarketing_notified_at);
        $this->assertDatabaseHas('program_reconciliation_logs', ['data_program_id' => $dataProgram->id, 'triggered_by' => AutoMatchService::TRIGGERED_BY]);
        Http::assertNothingSent();

        // Jalan berikutnya: tidak ada catatan riwayat baru
        $this->artisan('program:auto-match')->assertSuccessful();

        $this->assertSame(1, ProgramReconciliationLog::where('data_program_id', $dataProgram->id)->count());
    }

    public function test_whatsapp_cron_notifies_the_telemarketing_of_a_row_that_can_be_cut_once(): void
    {
        $dataProgram = $this->dataProgram(['status_potong_purchase' => 'BISA DI POTONG']);

        $this->artisan('program:notify-telemarketing')->assertSuccessful();

        $this->assertNotNull($dataProgram->fresh()->telemarketing_notified_at);
        Http::assertSent(fn (Request $request) => $request['recipient']['value'] === '6281200000001'
            && str_contains($request['message']['text'], 'BT07777'));

        $this->artisan('program:notify-telemarketing')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_cron_skips_rows_without_a_submission_and_rows_already_handled(): void
    {
        $withoutSubmission = $this->dataProgram(['row_hash' => 'dp_no_submission', 'kode_bt' => 'BT09999', 'dealer_name' => 'TOKO TANPA FORM']);
        $this->dataProgram(['row_hash' => 'dp_dealer_agreed', 'status_potong_purchase' => 'BISA DI POTONG', 'status_potong_ar' => 'DEALER SETUJU']);
        $this->dataProgram(['row_hash' => 'dp_notified', 'status_potong_purchase' => 'BISA DI POTONG', 'telemarketing_notified_at' => now()->subDay()]);

        $this->artisan('program:auto-match')->assertSuccessful();
        $this->artisan('program:notify-telemarketing')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, ProgramReconciliationLog::where('data_program_id', $withoutSubmission->id)->count());
    }

    public function test_auto_match_send_wa_false_switches_the_whatsapp_cron_off(): void
    {
        config(['services.auto_match.send_whatsapp' => false]);
        $dataProgram = $this->dataProgram(['status_potong_purchase' => 'BISA DI POTONG']);

        $this->artisan('program:notify-telemarketing')->assertSuccessful();

        $this->assertNull($dataProgram->fresh()->telemarketing_notified_at);
        Http::assertNothingSent();
    }

    public function test_a_corrected_submission_is_matched_again_on_the_next_run(): void
    {
        $dataProgram = $this->dataProgram();
        $submission = $this->submission(['net_pay' => 500000, 'dpp' => 500000]);

        $this->artisan('program:auto-match')->assertSuccessful();
        $this->assertSame('BELUM BISA POTONG', $dataProgram->fresh()->status_potong_purchase);

        // Tidak berubah: tidak dicek ulang
        $this->artisan('program:auto-match')->assertSuccessful();
        $this->assertSame(1, ProgramReconciliationLog::where('data_program_id', $dataProgram->id)->count());

        // Nominal di Form Program diperbaiki: dicek ulang
        $this->travel(1)->minutes();
        $submission->update(['net_pay' => 750000, 'dpp' => 750000]);

        $this->artisan('program:auto-match')->assertSuccessful();

        $this->assertSame('BISA DI POTONG', $dataProgram->fresh()->status_potong_purchase);
    }

    public function test_manual_whatsapp_marks_the_row_so_the_cron_does_not_resend(): void
    {
        $dataProgram = $this->dataProgram(['status_potong_purchase' => 'BISA DI POTONG']);

        $this->postJson("/api/data-program/{$dataProgram->id}/send-wa-telemarketing")->assertOk();
        $this->assertNotNull($dataProgram->fresh()->telemarketing_notified_at);

        $this->artisan('program:notify-telemarketing')->assertSuccessful();

        Http::assertSentCount(1);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dataProgram(array $attributes = []): DataProgram
    {
        return DataProgram::create($attributes + [
            'row_hash' => 'dp_auto_match',
            'dealer_name' => 'CV SINAR MAKMUR',
            'kode_bt' => 'BT07777',
            'program_name' => 'PROGRAM DSA JULI 2026',
            'region' => 'BIG BEKASI 1',
            'wajib_pajak' => 'PRIBADI NON PKP',
            'ppn' => 0,
            'net_pay' => 750000,
            'dpp' => 750000,
            'status_potong_purchase' => 'BELUM BISA POTONG',
            'status_potong_ar' => 'BELUM POTONG',
            'telemarketing_phone' => '081200000001',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function submission(array $attributes = []): ProgramSubmission
    {
        return ProgramSubmission::create($attributes + [
            'row_hash' => 'sub_auto_match',
            'dealer_name' => 'CV SINAR MAKMUR',
            'id_real' => 'BT07777',
            'program_name' => 'PROGRAM DSA JULI 2026',
            'region' => 'BIG BEKASI',
            'net_pay' => 750000,
            'dpp' => 750000,
            'credit_note_url' => 'https://drive.google.com/open?id=auto_cn',
            'agreement_url' => 'https://drive.google.com/open?id=auto_agr',
        ]);
    }
}

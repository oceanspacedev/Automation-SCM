<?php

namespace Tests\Feature;

use App\Models\AutomationSchedule;
use App\Models\DataProgram;
use App\Models\ProgramApprovalLog;
use App\Models\ProgramReconciliationLog;
use App\Models\ProgramSubmission;
use App\Models\User;
use App\Services\AutoMatchService;
use Carbon\Carbon;
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

        // API selain login, form publik & webhook butuh login
        $this->actingAs(User::factory()->create(['role' => 'admin']));

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

    public function test_whatsapp_cron_reminds_the_telemarketing_every_few_days_up_to_the_maximum(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 09:00'));
        $dataProgram = $this->dataProgram(['status_potong_purchase' => 'BISA DI POTONG']);
        // Baris lama yang hanya ditandai terkirim saat WA otomatis dipasang (tanpa WA sungguhan) tidak diingatkan
        $this->dataProgram(['row_hash' => 'dp_marked_only', 'kode_bt' => 'BT08888', 'dealer_name' => 'TOKO LAMA',
            'status_potong_purchase' => 'BISA DI POTONG', 'telemarketing_notified_at' => now()->subMonth()]);

        $this->artisan('program:notify-telemarketing')->assertSuccessful();
        Http::assertSentCount(1);

        // Bawaan: 3 hari setelah WA terakhir, paling banyak 3 kali, sekali di hari itu
        $sentCountAt = ['2026-10-22 09:00' => 1, '2026-10-23 09:00' => 2, '2026-10-23 14:00' => 2, '2026-10-26 09:00' => 3, '2026-10-29 09:00' => 4, '2026-11-01 09:00' => 4];
        foreach ($sentCountAt as $time => $sentCount) {
            $this->travelTo(Carbon::parse($time));
            $this->artisan('program:notify-telemarketing')->assertSuccessful();
            Http::assertSentCount($sentCount);
        }

        $this->assertSame(3, $dataProgram->fresh()->telemarketing_reminder_count);
        $this->assertSame(3, ProgramApprovalLog::where('data_program_id', $dataProgram->id)->where('event', ProgramApprovalLog::TELEMARKETING_REMINDED)->count());
        Http::assertSent(fn (Request $request) => str_contains($request['message']['text'], 'PENGINGAT KE-3: KLAIM BELUM DISETUJUI DEALER')
            && str_contains($request['message']['text'], 'BT07777'));
    }

    public function test_reminders_stop_once_the_dealer_approves_and_wait_again_after_a_postponement(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 09:00'));
        $approved = $this->dataProgram(['status_potong_purchase' => 'BISA DI POTONG']);
        $postponed = $this->dataProgram(['row_hash' => 'dp_postponed', 'kode_bt' => 'BT07778', 'dealer_name' => 'TOKO TUNDA', 'status_potong_purchase' => 'BISA DI POTONG']);
        $this->artisan('program:notify-telemarketing')->assertSuccessful();

        $approved->update(['status_potong_ar' => 'DEALER SETUJU (PROSES AR)']);
        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $postponed->update(['status_potong_ar' => 'PENDING DEALER']);
        ProgramApprovalLog::record($postponed, ProgramApprovalLog::POSTPONED, actor: ProgramApprovalLog::LINK_ACTORS['telemarketing']);

        // Sehari setelah ditunda belum diingatkan; tiga hari setelah ditunda diingatkan untuk menawarkan kembali
        $this->travelTo(Carbon::parse('2026-10-23 09:00'));
        $this->artisan('program:notify-telemarketing')->assertSuccessful();
        Http::assertSentCount(2);

        $this->travelTo(Carbon::parse('2026-10-25 09:00'));
        $this->artisan('program:notify-telemarketing')->assertSuccessful();
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => str_contains($request['message']['text'], 'PENGINGAT KE-1')
            && str_contains($request['message']['text'], 'TOKO TUNDA')
            && str_contains($request['message']['text'], 'DITUNDA dealer'));
        $this->assertSame(0, $approved->fresh()->telemarketing_reminder_count);
    }

    public function test_reminders_can_be_switched_off(): void
    {
        AutomationSchedule::forKey(AutomationSchedule::TELEMARKETING_WA)->update(['options' => ['reminder_every_days' => 0]]);
        $this->travelTo(Carbon::parse('2026-10-20 09:00'));
        $this->dataProgram(['status_potong_purchase' => 'BISA DI POTONG']);

        $this->artisan('program:notify-telemarketing')->assertSuccessful();
        $this->travelTo(Carbon::parse('2026-10-30 09:00'));
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

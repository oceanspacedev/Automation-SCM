<?php

namespace Tests\Feature;

use App\Models\AutomationSchedule;
use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\Draft;
use App\Models\Invoice;
use App\Models\ProgramSubmission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomationScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_schedule_is_due_on_its_interval_times_and_days(): void
    {
        $everyFifteen = AutomationSchedule::factory()->make(['interval_minutes' => 15]);
        $this->assertTrue($everyFifteen->isDue(Carbon::parse('2026-10-19 08:15')));
        $this->assertFalse($everyFifteen->isDue(Carbon::parse('2026-10-19 08:20')));

        $atTimes = AutomationSchedule::factory()->atTimes(['08:00', '13:30'])->make(['days' => [1, 2, 3, 4, 5]]);
        $this->assertTrue($atTimes->isDue(Carbon::parse('2026-10-19 13:30')));  // Senin
        $this->assertFalse($atTimes->isDue(Carbon::parse('2026-10-19 13:31')));
        $this->assertFalse($atTimes->isDue(Carbon::parse('2026-10-18 13:30'))); // Minggu

        $this->assertFalse(AutomationSchedule::factory()->inactive()->make()->isDue(Carbon::parse('2026-10-19 08:15')));
    }

    public function test_matching_runs_every_minute_and_the_whatsapp_job_only_at_its_saved_times(): void
    {
        $events = collect(app(Schedule::class)->events());
        $matching = $events->first(fn (Event $event) => str_contains((string) $event->command, 'program:auto-match'));
        $whatsApp = $events->first(fn (Event $event) => str_contains((string) $event->command, 'program:notify-telemarketing'));

        $this->travelTo(Carbon::parse('2026-10-20 10:07'));
        $this->assertTrue($matching->isDue($this->app) && $matching->filtersPass($this->app));
        $this->assertSame('scheduler-heartbeat', $events->first()->description);
        $this->assertFalse($whatsApp->filtersPass($this->app));

        // Bawaan: 09:00 dan 14:00 setiap hari
        $this->travelTo(Carbon::parse('2026-10-20 14:00'));
        $this->assertTrue($whatsApp->filtersPass($this->app));

        AutomationSchedule::forKey(AutomationSchedule::TELEMARKETING_WA)->update(['times' => ['10:07']]);

        $this->assertFalse($whatsApp->filtersPass($this->app));
        $this->travelTo(Carbon::parse('2026-10-21 10:07'));
        $this->assertTrue($whatsApp->filtersPass($this->app));
    }

    public function test_the_page_reports_whether_the_scheduler_ticked_in_the_last_two_minutes(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 09:00:30'));
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->getJson('/api/automation-schedules')
            ->assertOk()
            ->assertJsonPath('scheduler_alive', false)
            ->assertJsonPath('scheduler_last_seen', null);

        $this->artisan('schedule:test', ['--name' => 'scheduler-heartbeat'])->assertSuccessful();
        $tickedAt = now()->toIso8601String();

        $this->travel(90)->seconds();
        $this->actingAs($admin)
            ->getJson('/api/automation-schedules')
            ->assertJsonPath('scheduler_alive', true)
            ->assertJsonPath('scheduler_last_seen', $tickedAt);

        $this->actingAs($admin)
            ->getJson('/api/automation-schedules?status_only=true')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'success' => true,
                'scheduler_alive' => true,
                'scheduler_last_seen' => $tickedAt,
            ]);

        $this->travel(1)->minutes();
        $this->actingAs($admin)
            ->getJson('/api/automation-schedules')
            ->assertJsonPath('scheduler_alive', false)
            ->assertJsonPath('scheduler_last_seen', $tickedAt);

        $this->actingAs($admin)
            ->getJson('/api/automation-schedules?status_only=true')
            ->assertOk()
            ->assertJsonPath('scheduler_alive', false)
            ->assertJsonMissingPath('data');
    }

    public function test_admin_sets_run_times_and_invalid_times_are_rejected(): void
    {
        $this->putJson('/api/automation-schedules/telemarketing_wa', [])->assertUnauthorized();

        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->putJson('/api/automation-schedules/telemarketing_wa', ['is_active' => true, 'mode' => 'times', 'times' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['times']);

        $this->actingAs($admin)
            ->putJson('/api/automation-schedules/telemarketing_wa', ['is_active' => true, 'mode' => 'times', 'times' => ['25:00']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['times.0']);

        $this->actingAs($admin)
            ->putJson('/api/automation-schedules/telemarketing_wa', [
                'is_active' => true,
                'mode' => 'times',
                'times' => ['16:30', '08:00'],
                'days' => [1, 2, 3, 4, 5, 6, 7],
            ])
            ->assertOk()
            ->assertJsonPath('data.times', ['08:00', '16:30'])
            ->assertJsonPath('data.days', [])
            ->assertJsonPath('data.interval_minutes', null);
    }

    public function test_admin_sets_how_often_the_telemarketing_is_reminded(): void
    {
        $admin = User::factory()->create();

        // Bawaan: setiap 3 hari, paling banyak 3 kali
        $this->actingAs($admin)->getJson('/api/automation-schedules')
            ->assertOk()
            ->assertJsonPath('data.0.reminder_every_days', 3)
            ->assertJsonPath('data.0.reminder_max', 3);

        $this->actingAs($admin)
            ->putJson('/api/automation-schedules/telemarketing_wa', ['is_active' => true, 'mode' => 'times', 'times' => ['09:00'], 'reminder_every_days' => 4])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reminder_every_days']);

        $this->actingAs($admin)
            ->putJson('/api/automation-schedules/telemarketing_wa', ['is_active' => true, 'mode' => 'times', 'times' => ['09:00'], 'reminder_every_days' => 7, 'reminder_max' => 2])
            ->assertOk()
            ->assertJsonPath('data.reminder_every_days', 7)
            ->assertJsonPath('data.reminder_max', 2);

        $this->assertSame(7, AutomationSchedule::forKey(AutomationSchedule::TELEMARKETING_WA)->reminder_every_days);
    }

    public function test_run_now_whatsapps_the_telemarketing_and_records_the_result(): void
    {
        config([
            'services.wag.token' => 'fake_token',
            'services.wag.url' => 'https://waghub.mekayastudio.com',
            'services.auto_match.send_whatsapp' => true,
        ]);
        Http::fake(['https://waghub.mekayastudio.com/api/v1/messages' => Http::response(['success' => true, 'data' => ['id' => 'msg_1']], 200)]);

        $dataProgram = DataProgram::create([
            'row_hash' => 'dp_run_now', 'dealer_name' => 'CV SINAR MAKMUR', 'kode_bt' => 'BT07777',
            'program_name' => 'PROGRAM DSA JULI 2026', 'region' => 'BIG BEKASI 1', 'wajib_pajak' => 'PRIBADI NON PKP',
            'net_pay' => 750000, 'dpp' => 750000, 'status_potong_purchase' => 'BISA DI POTONG', 'status_potong_ar' => 'BELUM POTONG',
            'telemarketing_phone' => '081200000001',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/automation-schedules/telemarketing_wa/run')
            ->assertOk()
            ->assertJsonPath('data.last_result', 'WhatsApp telemarketing: 1 terkirim, 0 gagal (dicoba lagi berikutnya).');

        $this->assertNotNull($dataProgram->fresh()->telemarketing_notified_at);
        Http::assertSentCount(1);
    }

    public function test_invoice_upload_reminder_schedule_can_be_configured_and_run_now(): void
    {
        config([
            'services.wag.token' => 'fake_token',
            'services.wag.url' => 'https://waghub.mekayastudio.com',
        ]);
        Http::fake(['https://waghub.mekayastudio.com/api/v1/messages' => Http::response(['success' => true, 'data' => ['id' => 'msg_up_1']], 200)]);

        $admin = User::factory()->create();

        // Check it exists in API
        $this->actingAs($admin)
            ->getJson('/api/automation-schedules')
            ->assertOk()
            ->assertJsonPath('data.1.key', 'invoice_remind_upload')
            ->assertJsonPath('data.1.reminder_every_days', 3);

        // Update schedule
        $this->actingAs($admin)
            ->putJson('/api/automation-schedules/invoice_remind_upload', [
                'is_active' => true,
                'mode' => 'times',
                'times' => ['10:00', '15:00'],
                'days' => [1, 2, 3, 4, 5],
                'reminder_every_days' => 2,
                'reminder_max' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.times', ['10:00', '15:00'])
            ->assertJsonPath('data.reminder_every_days', 2)
            ->assertJsonPath('data.reminder_max', 5);

        // Run now triggers command and records run
        $this->actingAs($admin)
            ->postJson('/api/automation-schedules/invoice_remind_upload/run')
            ->assertOk()
            ->assertJsonPath('success', true);

        $schedule = AutomationSchedule::forKey(AutomationSchedule::INVOICE_REMIND_UPLOAD);
        $this->assertNotNull($schedule->last_run_at);
        $this->assertNotNull($schedule->last_result);
    }

    public function test_scheduled_commands_use_short_overlap_locks_instead_of_24_hour_default(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => $event->withoutOverlapping);

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertGreaterThan(0, $event->expiresAt, "Overlap lock for {$event->command} must be positive.");
            $this->assertLessThan(1440, $event->expiresAt, "Overlap lock for {$event->command} must not use 24h default.");
        }
    }

    public function test_idle_queue_and_ai_commands_skip_spawning_when_there_is_no_work(): void
    {
        $events = collect(app(Schedule::class)->events());
        $queueWorker = $events->first(fn (Event $event) => str_contains((string) $event->command, 'queue:work'));
        $autoAi = $events->first(fn (Event $event) => str_contains((string) $event->command, 'program:auto-analyze-ai'));

        $this->assertNotNull($queueWorker);
        $this->assertNotNull($autoAi);

        config(['queue.default' => 'sync']);
        $this->assertFalse($queueWorker->filtersPass($this->app));

        config(['queue.default' => 'database']);
        $this->assertFalse($queueWorker->filtersPass($this->app));

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subSecond()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        $this->assertTrue($queueWorker->filtersPass($this->app));

        $this->assertFalse($autoAi->filtersPass($this->app));

        ProgramSubmission::create([
            'row_hash' => 'sub_needs_ai_2026',
            'submission_timestamp' => '2026-11-24 10:00:00',
            'id_real' => 'BT01234',
            'dealer_name' => 'TOKO TEST AI',
            'program_name' => 'PROGRAM DSA NOV 2026',
            'cek_dokumen' => null,
            'status_potong_purchase' => null,
        ]);
        $this->assertTrue($autoAi->filtersPass($this->app));
    }

    public function test_remind_telemarketing_upload_filters_uploaded_invoices_in_sql_before_limit(): void
    {
        config([
            'services.wag.token' => 'fake_token',
            'services.wag.url' => 'https://waghub.mekayastudio.com',
        ]);
        Http::fake(['https://waghub.mekayastudio.com/api/v1/messages' => Http::response(['success' => true, 'data' => ['id' => 'msg_up_2']], 200)]);

        Dealer::create([
            'kode_bt' => 'BT09001',
            'id_realme' => 'DL09001',
            'dealer_name' => 'TOKO SUDAH UPLOAD',
            'region' => 'BIG BANDUNG',
            'telemarketing' => 'Rina',
            'telemarketing_phone' => '081200000011',
            'is_active' => true,
        ]);
        Dealer::create([
            'kode_bt' => 'BT09002',
            'id_realme' => 'DL09002',
            'dealer_name' => 'TOKO BELUM UPLOAD',
            'region' => 'BIG BANDUNG',
            'telemarketing' => 'Rina',
            'telemarketing_phone' => '081200000011',
            'is_active' => true,
        ]);

        $draftUploaded = Draft::create([
            'cn_number' => 'CN-UP-001',
            'dealer_code' => 'BT09001',
            'kode_bt' => 'BT09001',
            'dealer_name' => 'TOKO SUDAH UPLOAD',
            'region' => 'BIG BANDUNG',
            'program_name' => 'PROGRAM DSA NOV 2026',
            'support_amount' => 100000,
            'dpp' => 100000,
            'netpay' => 100000,
        ]);
        $uploadedInvoice = Invoice::create([
            'draft_id' => $draftUploaded->id,
            'invoice_number' => 'INV-UP-001',
            'invoice_type' => 'DSA',
            'dealer_code' => 'BT09001',
            'dealer_name' => 'TOKO SUDAH UPLOAD',
            'program_name' => 'PROGRAM DSA NOV 2026',
            'status' => 'generated',
            'support_amount' => 100000,
            'dpp' => 100000,
            'netpay' => 100000,
        ]);
        Invoice::where('id', $uploadedInvoice->id)->update(['created_at' => now()->subDays(10)]);

        ProgramSubmission::create([
            'row_hash' => 'sub_uploaded_inv_001',
            'id_real' => 'BT09001',
            'dealer_name' => 'TOKO SUDAH UPLOAD',
            'program_name' => 'PROGRAM DSA NOV 2026',
            'credit_note_url' => 'https://example.com/cn.pdf',
        ]);

        $draftPending = Draft::create([
            'cn_number' => 'CN-UP-002',
            'dealer_code' => 'BT09002',
            'kode_bt' => 'BT09002',
            'dealer_name' => 'TOKO BELUM UPLOAD',
            'region' => 'BIG BANDUNG',
            'program_name' => 'PROGRAM DSA NOV 2026',
            'support_amount' => 200000,
            'dpp' => 200000,
            'netpay' => 200000,
        ]);
        $pendingInvoice = Invoice::create([
            'draft_id' => $draftPending->id,
            'invoice_number' => 'INV-UP-002',
            'invoice_type' => 'DSA',
            'dealer_code' => 'BT09002',
            'dealer_name' => 'TOKO BELUM UPLOAD',
            'program_name' => 'PROGRAM DSA NOV 2026',
            'status' => 'generated',
            'support_amount' => 200000,
            'dpp' => 200000,
            'netpay' => 200000,
        ]);
        Invoice::where('id', $pendingInvoice->id)->update(['created_at' => now()->subDays(10)]);

        // Meski limit=1 dan invoice pertama sudah di-upload, invoice kedua yang belum di-upload tetap ditemukan & diingatkan
        $this->artisan('invoices:remind-telemarketing-upload', ['--days' => 1, '--limit' => 1])
            ->assertSuccessful();

        $this->assertNull($uploadedInvoice->fresh()->upload_reminded_at);
        $this->assertNotNull($pendingInvoice->fresh()->upload_reminded_at);
        $this->assertSame(1, (int) $pendingInvoice->fresh()->upload_reminder_count);
        Http::assertSentCount(1);
    }
}

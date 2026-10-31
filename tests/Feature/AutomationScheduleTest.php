<?php

namespace Tests\Feature;

use App\Models\AutomationSchedule;
use App\Models\DataProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->travel(1)->minutes();
        $this->actingAs($admin)
            ->getJson('/api/automation-schedules')
            ->assertJsonPath('scheduler_alive', false)
            ->assertJsonPath('scheduler_last_seen', $tickedAt);
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
}

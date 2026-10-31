<?php

namespace Tests\Feature;

use App\Models\ArContact;
use App\Models\DataProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProgramApprovalHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['X-Area' => 'jabar', 'X-Brand' => 'realme'];

    /**
     * Whether the fake WhatsApp gateway rejects messages to AR.
     */
    private bool $whatsAppToArFails = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin', 'email' => 'admin@contoh.test']));
        config([
            'services.wag.token' => 'fake_token',
            'services.wag.url' => 'https://waghub.mekayastudio.com',
            'services.auto_match.send_whatsapp' => true,
        ]);

        // WA ke AR bisa dibuat gagal; Google Spreadsheet dan lainnya selalu berhasil
        Http::fake(fn (Request $request) => $this->whatsAppToArFails && str_starts_with((string) ($request['client_reference'] ?? ''), 'DP-CLAIM-AR')
            ? Http::response(['message' => 'Nomor tidak terdaftar'], 422)
            : Http::response(['success' => true, 'data' => ['id' => 'msg_1']], 200));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dataProgram(array $attributes = [], string $rowHash = 'dp_approval'): DataProgram
    {
        return DataProgram::create($attributes + [
            'area' => 'jabar',
            'brand' => 'realme',
            'row_hash' => $rowHash,
            'dealer_name' => 'CV SINAR MAKMUR',
            'kode_bt' => 'BT07777',
            'program_name' => 'PROGRAM DSA JULI 2026',
            'region' => 'BIG BANDUNG',
            'telemarketing' => 'SALSABILA',
            'telemarketing_phone' => '081200000001',
            'net_pay' => 750000,
            'cek_dokumen' => 'LENGKAP',
            'status_potong_purchase' => 'BISA DI POTONG',
            'status_potong_ar' => 'BELUM POTONG',
        ]);
    }

    /**
     * Click the WhatsApp confirmation link of a row as telemarketing or AR.
     *
     * @param  array<string, string>  $data
     */
    private function confirm(DataProgram $row, string $role, array $data): TestResponse
    {
        return $this->post(URL::temporarySignedRoute('data-programs.confirm', now()->addDay(), ['id' => $row->id, 'role' => $role]), $data + ['role' => $role]);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function history(array $query = []): TestResponse
    {
        return $this->getJson('/api/data-program/approval-history?'.http_build_query($query), self::HEADERS)->assertOk();
    }

    public function test_each_step_from_the_whatsapp_to_telemarketing_until_the_cut_is_recorded(): void
    {
        ArContact::factory()->create(['area' => 'jabar', 'region' => 'BIG BANDUNG', 'name' => 'SITI AMINAH', 'whatsapp' => '081300000002']);
        $row = $this->dataProgram();

        $this->postJson("/api/data-program/{$row->id}/send-wa-telemarketing", [], self::HEADERS)->assertOk();
        // Nama di bawah tiap langkah: telemarketing toko, lalu AR region-nya di Master AR
        $this->history()
            ->assertJsonPath('data.0.stage', 'waiting_approval')
            ->assertJsonPath('data.0.steps.1.state', 'done')
            ->assertJsonPath('data.0.steps.1.actor', 'admin@contoh.test')
            ->assertJsonPath('data.0.steps.2.state', 'current')
            ->assertJsonPath('data.0.steps.*.person', ['Dokumen lengkap', 'SALSABILA', 'SALSABILA', 'SITI AMINAH', 'SITI AMINAH']);

        // Telemarketing menyetujui lewat link; WA ke AR terkirim otomatis
        $this->confirm($row, 'telemarketing', ['action' => 'setuju'])->assertOk();
        $this->history()
            ->assertJsonPath('data.0.stage', 'approved')
            ->assertJsonPath('data.0.steps.2.actor', 'Telemarketing (link WA)')
            ->assertJsonPath('data.0.steps.3.state', 'done')
            ->assertJsonPath('data.0.steps.3.actor', 'Otomatis')
            ->assertJsonPath('data.0.steps.4.state', 'current');

        $this->confirm($row, 'ar', ['action' => 'potong', 'no_pembayaran' => 'PAY/2026/10/0001'])->assertOk();

        $this->getJson("/api/data-program/{$row->id}/approval-history", self::HEADERS)
            ->assertOk()
            ->assertJsonPath('data.stage', 'cut')
            ->assertJsonPath('data.steps.*.state', ['done', 'done', 'done', 'done', 'done'])
            ->assertJsonPath('data.steps.4.note', 'No. Pembayaran: PAY/2026/10/0001')
            ->assertJsonPath('data.timeline.*.event', ['telemarketing_notified', 'approved', 'ar_notified', 'cut'])
            ->assertJsonPath('data.timeline.2.recipient', 'SITI AMINAH 081300000002')
            ->assertJsonPath('data.timeline.3.actor', 'AR (link WA)');
    }

    public function test_rows_are_grouped_by_stage_from_their_status_even_without_recorded_steps(): void
    {
        $this->dataProgram([], 'dp_1');
        $this->dataProgram(['telemarketing_notified_at' => now()], 'dp_2');
        $this->dataProgram(['status_potong_ar' => 'PENDING DEALER'], 'dp_3');
        $approved = $this->dataProgram(['status_potong_ar' => 'DEALER SETUJU (PROSES AR)'], 'dp_4');
        $cut = $this->dataProgram(['status_potong_purchase' => 'SUDAH POTONG', 'status_potong_ar' => 'DONE', 'tgl_potong_tf' => '10/20/2026', 'no_pembayaran' => 'PAY-1'], 'dp_5');
        // Dari spreadsheet: status AR "SUDAH POTONG" juga berarti sudah dipotong
        $this->dataProgram(['status_potong_ar' => 'SUDAH POTONG', 'tgl_potong_tf' => '08-Agu-2022'], 'dp_8');
        // Belum masuk alur, atau area lain
        $this->dataProgram(['status_potong_purchase' => 'BELUM BISA POTONG'], 'dp_6');
        $this->dataProgram(['area' => 'jateng'], 'dp_7');

        // Jumlah per tahap selalu sama dengan "Semua"
        $this->history()->assertJsonPath('counts', ['all' => 6, 'waiting_telemarketing' => 1, 'waiting_approval' => 1, 'postponed' => 1, 'approved' => 1, 'cut' => 2]);

        // Baris lama yang sudah disetujui: WA ke AR dianggap sudah terkirim saat itu, tinggal menunggu AR memotong
        $this->history(['stage' => 'approved'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id)
            ->assertJsonPath('data.0.steps.*.state', ['done', 'done', 'done', 'done', 'current']);
        $this->history(['stage' => 'postponed'])
            ->assertJsonPath('data.0.steps.*.state', ['done', 'done', 'postponed', 'waiting', 'waiting'])
            // Region tanpa AR di Master AR: WA ke nomor AR default
            ->assertJsonPath('data.0.steps.3.person', 'AR default');
        $this->history(['stage' => 'waiting_telemarketing'])->assertJsonPath('data.0.steps.*.state', ['done', 'current', 'waiting', 'waiting', 'waiting']);
        $cutRow = collect($this->history(['stage' => 'cut'])->assertJsonCount(2, 'data')->json('data'))->firstWhere('id', $cut->id);
        $this->assertSame('10/20/2026', $cutRow['steps'][4]['date']);
        $this->assertSame('No. Pembayaran: PAY-1', $cutRow['steps'][4]['note']);
        $this->history(['search' => 'tidak ada'])->assertJsonCount(0, 'data');
    }

    public function test_a_whatsapp_to_ar_that_fails_after_the_dealer_approves_shows_until_it_is_sent_again(): void
    {
        $row = $this->dataProgram(['telemarketing_notified_at' => now()]);
        $this->whatsAppToArFails = true;

        $this->confirm($row, 'telemarketing', ['action' => 'setuju'])->assertOk();
        $this->history(['stage' => 'approved'])
            ->assertJsonPath('data.0.steps.*.state', ['done', 'done', 'done', 'failed', 'waiting'])
            ->assertJsonPath('data.0.steps.3.actor', 'Otomatis');

        // Dikirim ulang manual dari halaman Data Program
        $this->whatsAppToArFails = false;
        $this->postJson("/api/data-program/{$row->id}/send-wa-ar", [], self::HEADERS)->assertOk();
        $this->history(['stage' => 'approved'])
            ->assertJsonPath('data.0.steps.3.state', 'done')
            ->assertJsonPath('data.0.steps.3.actor', 'admin@contoh.test')
            ->assertJsonPath('data.0.steps.4.state', 'current');
    }

    public function test_the_automatic_whatsapp_to_telemarketing_is_recorded(): void
    {
        $this->dataProgram();

        $this->artisan('program:notify-telemarketing')->assertSuccessful();

        $this->history()
            ->assertJsonPath('data.0.stage', 'waiting_approval')
            ->assertJsonPath('data.0.steps.1.actor', 'Otomatis')
            ->assertJsonPath('data.0.steps.1.recipient', 'SALSABILA 081200000001');
    }
}

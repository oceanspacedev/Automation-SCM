<?php

namespace Tests\Feature;

use App\Models\ArContact;
use App\Models\DataProgram;
use App\Models\User;
use App\Services\DataProgramSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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

    public function test_each_step_from_the_whatsapp_to_telemarketing_until_the_tax_check_is_recorded(): void
    {
        Cache::put(DataProgramSyncService::CACHE_KEY_SPREADSHEET_URL, 'https://script.google.com/macros/s/test_token/exec');
        ArContact::factory()->create(['area' => 'jabar', 'region' => 'BIG BANDUNG', 'name' => 'SITI AMINAH', 'whatsapp' => '081300000002']);
        $this->putJson('/api/ar-contacts/tax-team', ['name' => 'TIM PAJAK JABAR', 'whatsapp' => '081300000009'], self::HEADERS)->assertOk();
        $this->putJson('/api/ar-contacts/scm-team', ['name' => 'TIM SCM JABAR', 'whatsapp' => '081300000008'], self::HEADERS)->assertOk();
        $row = $this->dataProgram(['nilai_pph' => 15000]);

        $this->postJson("/api/data-program/{$row->id}/send-wa-telemarketing", [], self::HEADERS)->assertOk();
        // Nama di bawah tiap langkah: telemarketing toko, AR region-nya di Master AR, lalu Tim Pajak area-nya
        $this->history()
            ->assertJsonPath('data.0.stage', 'waiting_approval')
            ->assertJsonPath('data.0.steps.1.state', 'done')
            ->assertJsonPath('data.0.steps.1.actor', 'admin@contoh.test')
            ->assertJsonPath('data.0.steps.2.state', 'current')
            ->assertJsonPath('data.0.steps.*.person', ['Dokumen lengkap', 'SALSABILA', 'SALSABILA', 'SITI AMINAH', 'SITI AMINAH', 'TIM PAJAK JABAR', 'TIM PAJAK JABAR', 'TIM SCM JABAR']);

        // Telemarketing menyetujui lewat link; WA ke AR terkirim otomatis
        $this->confirm($row, 'telemarketing', ['action' => 'setuju'])->assertOk();
        $this->history()
            ->assertJsonPath('data.0.stage', 'approved')
            ->assertJsonPath('data.0.steps.2.actor', 'Telemarketing (link WA)')
            ->assertJsonPath('data.0.steps.3.state', 'done')
            ->assertJsonPath('data.0.steps.3.actor', 'Otomatis')
            ->assertJsonPath('data.0.steps.4.state', 'current');

        // AR memotong lewat link; WA ke Tim Pajak terkirim otomatis dengan link cek pajak
        $this->confirm($row, 'ar', ['action' => 'potong', 'no_pembayaran' => 'PAY/2026/10/0001'])
            ->assertOk()
            ->assertSee('WhatsApp terkirim ke Tim Pajak');
        Http::assertSent(fn (Request $request) => ($request['recipient']['value'] ?? null) === '6281300000009'
            && str_contains($request['message']['text'], 'CEK PAJAK KLAIM SUDAH DIPOTONG')
            && str_contains($request['message']['text'], "/dp/confirm/{$row->id}?")
            && str_contains($request['message']['text'], 'role=pajak'));
        $this->history(['stage' => 'cut'])
            ->assertJsonPath('data.0.stage_label', 'Dipotong AR · menunggu Tim Pajak')
            ->assertJsonPath('data.0.steps.*.state', ['done', 'done', 'done', 'done', 'done', 'done', 'current', 'waiting']);

        // Hanya link Tim Pajak yang bisa mengisi cek pajak
        $this->confirm($row, 'ar', ['action' => 'cek_pajak', 'cek_pajak_tarif' => '1'])->assertForbidden();
        $this->get(URL::temporarySignedRoute('data-programs.confirm', now()->addDay(), ['id' => $row->id, 'role' => 'pajak']))
            ->assertOk()
            ->assertSee('Cek Pajak Klaim yang Sudah Dipotong');
        // Tim Pajak menyimpan cek pajak; hasilnya terkirim otomatis ke WA Tim SCM
        $this->confirm($row, 'pajak', ['action' => 'cek_pajak', 'cek_pajak_tarif' => '16.500', 'selisih' => '-1.500', 'note_pph' => 'Kurang potong'])
            ->assertOk()
            ->assertSee('Cek Pajak Tersimpan')
            ->assertSee('Hasil terkirim ke WhatsApp Tim SCM');
        Http::assertSent(fn (Request $request) => ($request['recipient']['value'] ?? null) === '6281300000008'
            && str_contains($request['message']['text'], 'HASIL CEK PAJAK KLAIM')
            && str_contains($request['message']['text'], '*Note PPh:* Kurang potong')
            && str_contains($request['message']['text'], '*Selisih:* Rp -1.500'));

        $row->refresh();
        $this->assertSame('16.500', $row->cek_pajak_tarif);
        $this->assertSame(-1500.0, $row->selisih);
        $this->assertSame('Kurang potong', $row->note_pph);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'script.google.com')
            && ($request['rows'][0]['note_pph'] ?? null) === 'Kurang potong'
            && ($request['rows'][0]['cek_pajak_tarif'] ?? null) === '16.500');

        $this->getJson("/api/data-program/{$row->id}/approval-history", self::HEADERS)
            ->assertOk()
            ->assertJsonPath('data.stage', 'tax_checked')
            ->assertJsonPath('data.steps.*.state', ['done', 'done', 'done', 'done', 'done', 'done', 'done', 'done'])
            ->assertJsonPath('data.steps.4.note', 'No. Pembayaran: PAY/2026/10/0001')
            ->assertJsonPath('data.steps.6.note', 'Cek Pajak: 16.500 · Selisih: -1.500 · Note PPh: Kurang potong')
            ->assertJsonPath('data.timeline.*.event', ['telemarketing_notified', 'approved', 'ar_notified', 'cut', 'tax_notified', 'tax_checked', 'scm_notified'])
            ->assertJsonPath('data.timeline.2.recipient', 'SITI AMINAH 081300000002')
            ->assertJsonPath('data.timeline.3.actor', 'AR (link WA)')
            ->assertJsonPath('data.timeline.4.recipient', 'TIM PAJAK JABAR 081300000009')
            ->assertJsonPath('data.timeline.5.actor', 'Tim Pajak (link WA)')
            ->assertJsonPath('data.timeline.6.recipient', 'TIM SCM JABAR 081300000008');
    }

    public function test_the_confirmation_page_opens_the_uploaded_cn_agreement_and_faktur(): void
    {
        $row = $this->dataProgram([
            'status_potong_purchase' => 'SUDAH POTONG',
            'status_potong_ar' => 'DONE',
            'wajib_pajak' => 'PRIBADI',
            'cn' => 'https://files.contoh.test/cn-2610000201.pdf',
            'agrement' => 'https://files.contoh.test/agreement-sinar.pdf',
            'cek_fp' => '-',
        ]);

        $this->get(URL::temporarySignedRoute('data-programs.confirm', now()->addDay(), ['id' => $row->id, 'role' => 'pajak']))
            ->assertOk()
            ->assertSee('href="https://files.contoh.test/cn-2610000201.pdf"', false)
            ->assertSee('href="https://files.contoh.test/agreement-sinar.pdf"', false)
            ->assertSee('Faktur Pajak tidak wajib.');

        // Toko PKP tanpa faktur: tertulis belum ada
        $row->update(['wajib_pajak' => 'BADAN']);
        $this->get(URL::temporarySignedRoute('data-programs.confirm', now()->addDay(), ['id' => $row->id, 'role' => 'telemarketing']))
            ->assertOk()
            ->assertSee('href="https://files.contoh.test/cn-2610000201.pdf"', false)
            ->assertSee('Faktur Pajak belum ada.')
            ->assertDontSee('tidak wajib');
    }

    public function test_a_tax_check_result_the_scm_team_did_not_get_can_be_sent_again_from_riwayat_approval(): void
    {
        $this->putJson('/api/ar-contacts/tax-team', ['name' => 'TIM PAJAK JABAR', 'whatsapp' => '081300000009'], self::HEADERS)->assertOk();
        $row = $this->dataProgram(['status_potong_purchase' => 'SUDAH POTONG', 'status_potong_ar' => 'DONE', 'tax_notified_at' => now()]);

        // Nomor Tim SCM belum diisi: cek pajak tetap tersimpan, WA ke Tim SCM tercatat gagal
        $this->confirm($row, 'pajak', ['action' => 'cek_pajak', 'cek_pajak_tarif' => '15.000', 'selisih' => '0', 'note_pph' => 'OK'])
            ->assertOk()
            ->assertSee('WhatsApp ke Tim SCM gagal terkirim');
        $this->history(['stage' => 'tax_checked'])->assertJsonPath('data.0.steps.7.state', 'failed');

        $this->putJson('/api/ar-contacts/scm-team', ['name' => 'Tim SCM', 'whatsapp' => '081300000008'], self::HEADERS)->assertOk();
        $this->postJson("/api/data-program/{$row->id}/send-wa-scm", [], self::HEADERS)->assertOk();
        $this->history(['stage' => 'tax_checked'])
            ->assertJsonPath('data.0.steps.7.state', 'done')
            ->assertJsonPath('data.0.steps.7.actor', 'admin@contoh.test');

        // Baris yang belum dicek Tim Pajak belum punya hasil untuk Tim SCM
        $notChecked = $this->dataProgram(['status_potong_purchase' => 'SUDAH POTONG', 'status_potong_ar' => 'DONE'], 'dp_not_checked');
        $this->postJson("/api/data-program/{$notChecked->id}/send-wa-scm", [], self::HEADERS)->assertStatus(422);
    }

    public function test_a_whatsapp_to_the_tax_team_without_a_number_can_be_sent_again_from_riwayat_approval(): void
    {
        $row = $this->dataProgram(['status_potong_ar' => 'DEALER SETUJU (PROSES AR)', 'telemarketing_notified_at' => now()]);

        // Nomor Tim Pajak belum diisi: potong tetap tersimpan, WA ke Tim Pajak tercatat gagal
        $this->confirm($row, 'ar', ['action' => 'potong', 'no_pembayaran' => 'PAY-2'])
            ->assertOk()
            ->assertSee('WhatsApp ke Tim Pajak gagal terkirim');
        $this->history(['stage' => 'cut'])->assertJsonPath('data.0.steps.*.state', ['done', 'done', 'done', 'done', 'done', 'failed', 'waiting', 'waiting']);

        $this->putJson('/api/ar-contacts/tax-team', ['name' => 'Tim Pajak', 'whatsapp' => 'bukan nomor'], self::HEADERS)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['whatsapp']);
        $this->putJson('/api/ar-contacts/tax-team', ['name' => 'Tim Pajak', 'whatsapp' => '081300000009'], self::HEADERS)->assertOk();
        // Tim Pajak diatur per area
        $this->getJson('/api/ar-contacts/tax-team', ['X-Area' => 'jateng'])->assertJsonPath('data.whatsapp', null);

        $this->postJson("/api/data-program/{$row->id}/send-wa-tax", [], self::HEADERS)->assertOk();
        $this->history(['stage' => 'cut'])
            ->assertJsonPath('data.0.steps.5.state', 'done')
            ->assertJsonPath('data.0.steps.5.actor', 'admin@contoh.test')
            ->assertJsonPath('data.0.steps.6.state', 'current');

        // Baris yang belum dipotong AR belum perlu dicek Tim Pajak
        $notCut = $this->dataProgram([], 'dp_not_cut');
        $this->postJson("/api/data-program/{$notCut->id}/send-wa-tax", [], self::HEADERS)->assertStatus(422);
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
        // Cek Pajak sudah diisi di spreadsheet: sudah dicek pajak
        $this->dataProgram(['status_potong_purchase' => 'SUDAH POTONG', 'status_potong_ar' => 'DONE', 'cek_pajak_tarif' => '15000'], 'dp_9');
        // Belum masuk alur, atau area lain
        $this->dataProgram(['status_potong_purchase' => 'BELUM BISA POTONG'], 'dp_6');
        $this->dataProgram(['area' => 'jateng'], 'dp_7');

        // Jumlah per tahap selalu sama dengan "Semua"
        $this->history()->assertJsonPath('counts', ['all' => 7, 'waiting_telemarketing' => 1, 'waiting_approval' => 1, 'postponed' => 1, 'approved' => 1, 'cut' => 2, 'tax_checked' => 1]);

        // Baris lama yang sudah disetujui: WA ke AR dianggap sudah terkirim saat itu, tinggal menunggu AR memotong
        $this->history(['stage' => 'approved'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id)
            ->assertJsonPath('data.0.steps.*.state', ['done', 'done', 'done', 'done', 'current', 'waiting', 'waiting', 'waiting']);
        $this->history(['stage' => 'postponed'])
            ->assertJsonPath('data.0.steps.*.state', ['done', 'done', 'postponed', 'waiting', 'waiting', 'waiting', 'waiting', 'waiting'])
            // Region tanpa AR di Master AR: WA ke nomor AR default
            ->assertJsonPath('data.0.steps.3.person', 'Nomor AR default');
        $this->history(['stage' => 'waiting_telemarketing'])->assertJsonPath('data.0.steps.*.state', ['done', 'current', 'waiting', 'waiting', 'waiting', 'waiting', 'waiting', 'waiting']);
        $cutRow = collect($this->history(['stage' => 'cut'])->assertJsonCount(2, 'data')->json('data'))->firstWhere('id', $cut->id);
        $this->assertSame('10/20/2026', $cutRow['steps'][4]['date']);
        $this->assertSame('No. Pembayaran: PAY-1', $cutRow['steps'][4]['note']);
        $this->assertSame('current', $cutRow['steps'][5]['state']);
        // Dicek di spreadsheet (bukan lewat link Tim Pajak): tidak ada WA hasil ke SCM yang ditunggu
        $this->history(['stage' => 'tax_checked'])->assertJsonPath('data.0.steps.*.state', ['done', 'done', 'done', 'done', 'done', 'done', 'done', 'done']);
        $this->history(['search' => 'tidak ada'])->assertJsonCount(0, 'data');
    }

    public function test_a_whatsapp_to_ar_that_fails_after_the_dealer_approves_shows_until_it_is_sent_again(): void
    {
        $row = $this->dataProgram(['telemarketing_notified_at' => now()]);
        $this->whatsAppToArFails = true;

        $this->confirm($row, 'telemarketing', ['action' => 'setuju'])->assertOk();
        $this->history(['stage' => 'approved'])
            ->assertJsonPath('data.0.steps.*.state', ['done', 'done', 'done', 'failed', 'waiting', 'waiting', 'waiting', 'waiting'])
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

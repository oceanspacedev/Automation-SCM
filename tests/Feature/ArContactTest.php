<?php

namespace Tests\Feature;

use App\Area;
use App\Models\ArContact;
use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ArContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // API selain login, form publik & webhook butuh login
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_master_ar_has_one_ar_per_area(): void
    {
        $this->postJson('/api/ar-contacts', ['area' => 'jabar', 'name' => 'RINA', 'whatsapp' => '081234567890'])
            ->assertCreated()
            ->assertJsonPath('data.area', 'jabar');

        $this->postJson('/api/ar-contacts', ['area' => 'jabar', 'name' => 'DEWI', 'whatsapp' => '081111111111'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['area']);

        $this->postJson('/api/ar-contacts', ['area' => 'jateng', 'name' => 'DEWI', 'whatsapp' => 'nomor wa'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['whatsapp']);

        $this->postJson('/api/ar-contacts', ['area' => 'jateng', 'name' => 'DEWI', 'whatsapp' => '081111111111'])
            ->assertCreated();

        // Satu halaman untuk kedua area, apa pun area yang sedang dibuka
        $this->getJson('/api/ar-contacts', ['X-Area' => 'jateng'])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'RINA')
            ->assertJsonPath('data.1.name', 'DEWI');

        $rows = $this->downloadedSpreadsheetRows($this->get('/api/ar-contacts/export')->assertOk());
        $this->assertSame([['AREA', 'NAMA AR', 'NO WHATSAPP', 'STATUS'], ['Jawa Barat', 'RINA', '081234567890', 'Aktif'], ['Jawa Tengah', 'DEWI', '081111111111', 'Aktif']], $rows);
    }

    public function test_dealer_setuju_is_forwarded_to_the_ar_of_the_area(): void
    {
        config(['services.wag.token' => 'fake-token-test', 'services.wag.ar_phone' => '081224290502']);
        Http::fake(['*/api/v1/messages' => Http::response(['data' => ['id' => 'msg-1']], 201)]);

        ArContact::factory()->create(['name' => 'RINA', 'whatsapp' => '081234567890']);
        ArContact::factory()->forArea(Area::Jateng)->inactive()->create(['name' => 'DEWI', 'whatsapp' => '081111111111']);

        // Region apa pun di Jawa Barat ke AR Jawa Barat
        $dp = DataProgram::create(['area' => 'jabar', 'row_hash' => 'row_5', 'dealer_name' => 'NEWCO CELL', 'region' => 'BIG KARAWANG', 'big_region' => 'BIG CIREBON']);
        $this->post(URL::temporarySignedRoute('data-programs.confirm', now()->addDays(7), ['id' => $dp->id, 'role' => 'telemarketing']), ['action' => 'setuju'])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/messages')
            && $request['recipient']['value'] === '6281234567890'
            && str_contains($request['message']['text'], 'Halo RINA'));

        // AR Jawa Tengah nonaktif: pengajuan Jawa Tengah ke nomor AR default
        $submission = ProgramSubmission::create(['area' => 'jateng', 'region' => 'SOLO', 'dealer_name' => 'ABADI CELL', 'row_hash' => 'hash_ar_default']);
        $this->post(URL::temporarySignedRoute('program-submissions.confirm', now()->addDays(7), ['id' => $submission->id, 'role' => 'telemarketing']), ['action' => 'setuju'])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/messages')
            && $request['recipient']['value'] === '6281224290502'
            && str_contains($request['message']['text'], 'Halo Tim AR'));
    }

    public function test_per_region_ars_become_one_ar_per_area(): void
    {
        $migration = require database_path('migrations/2026_11_04_144744_make_ar_contacts_one_per_area.php');
        $migration->down();

        // AR lama per region: di Jawa Barat dua region memakai RINA, satu region memakai ANI
        DB::table('ar_contacts')->insert([
            ['area' => 'jabar', 'region' => 'BANDUNG', 'name' => 'ANI', 'whatsapp' => '081300000003', 'is_active' => true],
            ['area' => 'jabar', 'region' => 'BIG CIREBON', 'name' => 'RINA', 'whatsapp' => '081234567890', 'is_active' => true],
            ['area' => 'jabar', 'region' => 'BIG TASIK', 'name' => 'RINA', 'whatsapp' => '081234567890', 'is_active' => true],
            ['area' => 'jateng', 'region' => 'BIG SOLO', 'name' => 'DEWI', 'whatsapp' => '081111111111', 'is_active' => true],
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('ar_contacts', 'region'));
        $this->assertSame(['jabar' => 'RINA', 'jateng' => 'DEWI'], DB::table('ar_contacts')->orderBy('area')->pluck('name', 'area')->all());
    }
}

<?php

namespace Tests\Feature;

use App\Area;
use App\Models\ArContact;
use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ArContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_ar_has_one_ar_per_region_in_each_area(): void
    {
        $this->postJson('/api/ar-contacts', ['region' => 'big cirebon', 'name' => 'RINA', 'whatsapp' => '081234567890'], ['X-Area' => 'jabar'])
            ->assertCreated()
            ->assertJsonPath('data.region', 'BIG CIREBON')
            ->assertJsonPath('data.area', 'jabar');

        $this->postJson('/api/ar-contacts', ['region' => 'BIG CIREBON', 'name' => 'DEWI', 'whatsapp' => '081111111111'], ['X-Area' => 'jabar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['region']);

        $this->postJson('/api/ar-contacts', ['region' => 'BIG CIREBON', 'name' => 'DEWI', 'whatsapp' => 'nomor wa'], ['X-Area' => 'jateng'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['whatsapp']);

        // Region bernama sama di area lain punya AR sendiri
        $this->postJson('/api/ar-contacts', ['region' => 'BIG CIREBON', 'name' => 'DEWI', 'whatsapp' => '081111111111'], ['X-Area' => 'jateng'])
            ->assertCreated();

        $this->getJson('/api/ar-contacts', ['X-Area' => 'jateng'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'DEWI');
    }

    public function test_dealer_setuju_is_forwarded_to_the_ar_of_the_dealer_region(): void
    {
        config(['services.wag.token' => 'fake-token-test', 'services.wag.ar_phone' => '081224290502']);
        Http::fake(['*/api/v1/messages' => Http::response(['data' => ['id' => 'msg-1']], 201)]);

        ArContact::factory()->create(['region' => 'BIG CIREBON', 'name' => 'RINA', 'whatsapp' => '081234567890']);
        ArContact::factory()->forArea(Area::Jateng)->create(['region' => 'BIG KARAWANG', 'name' => 'DEWI', 'whatsapp' => '081111111111']);

        // Region Data Program kosong: AR dicari dari Big Region
        $dp = DataProgram::create(['area' => 'jabar', 'row_hash' => 'row_5', 'dealer_name' => 'NEWCO CELL', 'region' => null, 'big_region' => 'big cirebon']);
        $this->post(URL::temporarySignedRoute('data-programs.confirm', now()->addDays(7), ['id' => $dp->id, 'role' => 'telemarketing']), ['action' => 'setuju'])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/messages')
            && $request['recipient']['value'] === '6281234567890'
            && str_contains($request['message']['text'], 'Halo RINA'));

        // AR BIG KARAWANG hanya ada di Jawa Tengah: pengajuan Jawa Barat tetap ke nomor AR default
        $submission = ProgramSubmission::create(['area' => 'jabar', 'region' => 'BIG KARAWANG', 'dealer_name' => 'ABADI CELL', 'row_hash' => 'hash_ar_default']);
        $this->post(URL::temporarySignedRoute('program-submissions.confirm', now()->addDays(7), ['id' => $submission->id, 'role' => 'telemarketing']), ['action' => 'setuju'])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/messages')
            && $request['recipient']['value'] === '6281224290502'
            && str_contains($request['message']['text'], 'Halo Tim AR'));
    }
}

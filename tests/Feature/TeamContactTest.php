<?php

namespace Tests\Feature;

use App\Models\DataProgram;
use App\Models\TeamContact;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_tax_and_scm_team_members_can_be_added_edited_switched_off_and_deleted(): void
    {
        $id = $this->postJson('/api/team-contacts', ['team' => 'tax', 'name' => ' Rina ', 'whatsapp' => '081300000001'])
            ->assertCreated()
            ->assertJsonPath('message', 'Tim Pajak berhasil ditambahkan.')
            ->assertJsonPath('data.name', 'Rina')
            ->json('data.id');
        $this->postJson('/api/team-contacts', ['team' => 'scm', 'name' => 'Budi', 'whatsapp' => 'bukan nomor'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['whatsapp']);
        $this->postJson('/api/team-contacts', ['team' => 'keuangan', 'name' => 'Budi', 'whatsapp' => '081300000002'])
            ->assertJsonValidationErrors(['team']);

        $this->putJson("/api/team-contacts/{$id}", ['team' => 'scm', 'name' => 'Rina', 'whatsapp' => '081300000003'])->assertOk();
        $this->assertDatabaseHas('team_contacts', ['id' => $id, 'team' => 'scm', 'whatsapp' => '081300000003', 'is_active' => true]);

        $this->patchJson("/api/team-contacts/{$id}/toggle-status")->assertOk()->assertJsonPath('data.is_active', false);

        // One list for every area
        $this->getJson('/api/team-contacts', ['X-Area' => 'jateng'])->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/team-contacts/{$id}")->assertOk();
        $this->assertDatabaseCount('team_contacts', 0);
    }

    public function test_the_tax_check_whatsapp_goes_to_every_active_member_of_the_tax_team(): void
    {
        config(['services.wag.token' => 'fake_token']);
        Http::fake(fn (Request $request) => $request['recipient']['value'] === '6281300000002'
            ? Http::response(['message' => 'Nomor tidak terdaftar'], 422)
            : Http::response(['data' => ['id' => 'msg_1']], 201));

        TeamContact::factory()->create(['name' => 'Rina', 'whatsapp' => '081300000001']);
        TeamContact::factory()->create(['name' => 'Andi', 'whatsapp' => '081300000002']);
        TeamContact::factory()->inactive()->create(['name' => 'Dodi', 'whatsapp' => '081300000003']);
        TeamContact::factory()->scm()->create(['name' => 'Sari', 'whatsapp' => '081300000004']);
        $row = DataProgram::create(['area' => 'jateng', 'row_hash' => 'dp_team', 'dealer_name' => 'CV SINAR', 'kode_bt' => 'BT01234']);

        $result = app(WhatsAppService::class)->sendDataProgramTaxCheckNotification($row);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Rina (6281300000001)', $result['message']);
        $this->assertStringContainsString('Gagal ke Andi (6281300000002)', $result['message']);
        Http::assertSent(fn (Request $request) => $request['recipient']['value'] === '6281300000001' && str_contains($request['message']['text'], 'Halo Rina'));
        Http::assertSent(fn (Request $request) => $request['recipient']['value'] === '6281300000002' && str_contains($request['message']['text'], 'Halo Andi'));
        // Inactive members and the SCM team get nothing
        Http::assertNotSent(fn (Request $request) => in_array($request['recipient']['value'], ['6281300000003', '6281300000004'], true));
    }

    public function test_a_team_without_active_members_reports_that_its_number_is_missing(): void
    {
        config(['services.wag.token' => 'fake_token']);
        Http::fake();
        TeamContact::factory()->scm()->inactive()->create();
        $row = DataProgram::create(['area' => 'jabar', 'row_hash' => 'dp_team_2', 'dealer_name' => 'CV SINAR']);

        $result = app(WhatsAppService::class)->sendDataProgramScmNotification($row);

        $this->assertSame(['success' => false, 'message' => 'Nomor WhatsApp Tim SCM belum diisi di Master Tim Pajak & SCM.', 'provider_id' => null], $result);
        Http::assertNothingSent();
    }
}

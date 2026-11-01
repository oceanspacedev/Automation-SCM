<?php

namespace Tests\Feature;

use App\Models\DataProgram;
use App\Models\Draft;
use App\Models\MasterProgram;
use App\Models\User;
use App\Services\DataProgramSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MasterProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // API selain login, form publik & webhook butuh login
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_can_list_master_programs(): void
    {
        $response = $this->getJson('/api/programs');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data',
                'total',
                'current_page',
                'last_page',
            ],
            'stats' => [
                'total',
                'active',
                'inactive',
            ],
            'categories',
        ]);
    }

    public function test_can_get_active_program_options(): void
    {
        MasterProgram::create([
            'name' => 'PROGRAM KHUSUS TEST OPTIONS',
            'category' => 'DSA',
            'period' => 'Maret 2026',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/programs/options');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertContains('PROGRAM KHUSUS TEST OPTIONS', $response->json('names'));
    }

    public function test_can_create_new_master_program(): void
    {
        $payload = [
            'name' => 'PROGRAM PROMOTION RAMADHAN 2026',
            'code' => 'PRM-RMD-26',
            'category' => 'PROMOTION',
            'period' => 'Maret - April 2026',
            'description' => 'Program promo Ramadhan untuk dealer Realme',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/programs', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data' => [
                'name' => 'PROGRAM PROMOTION RAMADHAN 2026',
                'category' => 'PROMOTION',
            ],
        ]);

        $this->assertDatabaseHas('master_programs', [
            'name' => 'PROGRAM PROMOTION RAMADHAN 2026',
            'category' => 'PROMOTION',
        ]);
    }

    public function test_create_program_validates_required_and_unique_name(): void
    {
        // 1. Missing name
        $response = $this->postJson('/api/programs', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);

        // 2. Duplicate name
        MasterProgram::create([
            'name' => 'PROGRAM DUPLICATE CHECK',
            'is_active' => true,
        ]);

        $dupResponse = $this->postJson('/api/programs', [
            'name' => 'PROGRAM DUPLICATE CHECK',
        ]);
        $dupResponse->assertStatus(422);
        $dupResponse->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_master_program(): void
    {
        $program = MasterProgram::create([
            'name' => 'PROGRAM AWAL UPDATE',
            'category' => 'DSA',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/programs/{$program->id}", [
            'name' => 'PROGRAM SETELAH UPDATE',
            'category' => 'SO',
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $program->id,
                'name' => 'PROGRAM SETELAH UPDATE',
                'category' => 'SO',
                'is_active' => false,
            ],
        ]);

        $this->assertDatabaseHas('master_programs', [
            'id' => $program->id,
            'name' => 'PROGRAM SETELAH UPDATE',
            'is_active' => false,
        ]);
    }

    public function test_can_toggle_program_status(): void
    {
        $program = MasterProgram::create([
            'name' => 'PROGRAM TOGGLE TEST',
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/programs/{$program->id}/toggle-status");

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.is_active'));

        $this->assertDatabaseHas('master_programs', [
            'id' => $program->id,
            'is_active' => false,
        ]);
    }

    public function test_can_delete_master_program(): void
    {
        $program = MasterProgram::create([
            'name' => 'PROGRAM TO DELETE',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/programs/{$program->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('master_programs', [
            'id' => $program->id,
        ]);
    }

    public function test_program_submissions_form_options_includes_master_programs(): void
    {
        MasterProgram::create([
            'name' => 'PROGRAM INCLUDED IN FORM OPTIONS 2026',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/program-submissions/form-options');

        $response->assertStatus(200);
        $this->assertContains('PROGRAM INCLUDED IN FORM OPTIONS 2026', $response->json('programs'));
    }

    public function test_new_program_gets_next_sequential_code_when_code_is_empty(): void
    {
        MasterProgram::create(['code' => 'PROGRAM 99', 'name' => 'PROGRAM LAMA 99']);
        MasterProgram::create(['code' => 'PROGRAM 343', 'name' => 'PROGRAM DEALER CLUB C100I AGUSTUS 2026']);

        $this->getJson('/api/programs')->assertJsonPath('next_code', 'PROGRAM 344');

        $this->postJson('/api/programs', [
            'name' => 'PROGRAM DSA SEPTEMBER 2026',
            'period' => 'Periode 1-30 September 2026',
        ])->assertCreated()->assertJsonPath('data.code', 'PROGRAM 344');
    }

    public function test_a_saved_program_fills_its_code_into_data_program_rows_and_drafts_without_one(): void
    {
        Cache::put(DataProgramSyncService::CACHE_KEY_SPREADSHEET_URL, 'https://script.google.com/macros/s/test_token/exec');
        Http::fake(['https://script.google.com/macros/s/*' => Http::response(['success' => true, 'updated_count' => 1], 200)]);
        MasterProgram::create(['code' => 'PROGRAM 345', 'name' => 'PROGRAM DSA JANUARI 2026']);

        $draft = Draft::create(['area' => 'jabar', 'dealer_name' => 'A1 CELL', 'program_name' => 'PROGRAM SEPTEMBER 2028', 'support_amount' => 1, 'status' => 'ready']);
        $fromDraft = DataProgram::create(['row_hash' => "draft_{$draft->id}", 'draft_id' => $draft->id, 'dealer_name' => 'A1 CELL', 'kode_bt' => 'BT06910', 'program_name' => 'PROGRAM SEPTEMBER 2028']);
        $fromSheet = DataProgram::create(['row_hash' => 'row_12', 'dealer_name' => 'TOKO B', 'program' => '-', 'program_name' => 'PROGRAM SEPTEMBER 2028']);
        $ownCode = DataProgram::create(['row_hash' => 'row_13', 'dealer_name' => 'TOKO C', 'program' => 'PROGRAM 300', 'program_name' => 'PROGRAM SEPTEMBER 2028']);

        // Program baru di Master Program: kolom Program yang masih kosong langsung terisi kodenya, juga di spreadsheet
        $this->postJson('/api/programs', ['name' => 'PROGRAM SEPTEMBER 2028'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'PROGRAM 346')
            ->assertJsonPath('data_program_filled', 2);

        $this->assertSame('PROGRAM 346', $fromDraft->fresh()->program);
        $this->assertSame('PROGRAM 346', $fromSheet->fresh()->program);
        $this->assertSame('PROGRAM 300', $ownCode->fresh()->program);
        $this->assertSame('PROGRAM 346', $draft->fresh()->program_code);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'script.google.com')
            && collect($request['rows'] ?? [])->pluck('program')->unique()->values()->all() === ['PROGRAM 346']);

        // Kode diganti: baris yang masih memakai kode lama ikut berganti, kode yang diisi sendiri tetap
        $program = MasterProgram::where('name', 'PROGRAM SEPTEMBER 2028')->firstOrFail();
        $this->putJson("/api/programs/{$program->id}", ['name' => 'PROGRAM SEPTEMBER 2028', 'code' => '347'])
            ->assertOk()
            ->assertJsonPath('data_program_filled', 2);

        $this->assertSame('PROGRAM 347', $fromDraft->fresh()->program);
        $this->assertSame('PROGRAM 347', $draft->fresh()->program_code);
        $this->assertSame('PROGRAM 300', $ownCode->fresh()->program);
    }

    public function test_program_code_must_be_unique_after_normalization(): void
    {
        MasterProgram::create(['code' => 'PROGRAM 326', 'name' => 'PROGRAM ST SPECIAL DEALER MEI 2026']);

        $this->postJson('/api/programs', [
            'code' => 'program326',
            'name' => 'PROGRAM LAIN',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_exported_programs_use_the_import_layout_and_can_be_imported_again(): void
    {
        MasterProgram::create(['code' => 'PROGRAM 900', 'name' => 'PROGRAM EXPORT AKTIF', 'period' => 'Periode 1-31 Oktober 2026', 'is_active' => true]);
        MasterProgram::create(['code' => 'PROGRAM 901', 'name' => 'PROGRAM EXPORT NONAKTIF', 'is_active' => false]);

        $response = $this->get('/api/programs/export?search=PROGRAM%20EXPORT&is_active=true')->assertOk();
        $this->assertSame([
            ['PROGRAM', 'NAMA PROGRAM', 'PERIODE', 'STATUS'],
            ['PROGRAM 900', 'PROGRAM EXPORT AKTIF', 'Periode 1-31 Oktober 2026', 'Aktif'],
        ], $this->downloadedSpreadsheetRows($response));

        MasterProgram::where('code', 'PROGRAM 900')->update(['period' => null]);
        $file = $this->saveDownloadedSpreadsheet($response);

        $this->post('/api/programs/import', ['file' => new UploadedFile($file, 'export.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('updated', 1);
        $this->assertDatabaseHas('master_programs', ['code' => 'PROGRAM 900', 'period' => 'Periode 1-31 Oktober 2026']);
    }

    public function test_can_import_master_programs_from_excel(): void
    {
        MasterProgram::updateOrCreate(['name' => 'PROGRAM DSA MEI 2026'], ['code' => null, 'period' => 'Mei 2026']);
        $initialCount = MasterProgram::count();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            [null, null, null, null, null, null],
            ['PROGRAM', 'NAMA PROGRAM', 'PERIODE', 'SALES', 'TELEMARKETING', 'NO WA TELEMARKETING'],
            ['PROGRAM 325', 'PROGRAM DSA MEI 2026', 'Periode 1-31 Mei 2026', 'ANDI', 'SITI', '081234567890'],
            ['PROGRAM 326', 'PROGRAM ST SPECIAL DEALER MEI 2026', 'Periode 1-31 Mei 2026', null, null, null],
            ['PROGRAM 344', null, null, null, null, null],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'programs').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->post('/api/programs/import', [
            'file' => new UploadedFile($path, 'programs.xlsx', null, null, true),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('skipped', 1);

        // Kolom SALES / TELEMARKETING di file diabaikan (sales & telemarketing ada di Master Dealer)
        $this->assertDatabaseHas('master_programs', [
            'code' => 'PROGRAM 325',
            'name' => 'PROGRAM DSA MEI 2026',
            'period' => 'Periode 1-31 Mei 2026',
        ]);
        $this->assertDatabaseHas('master_programs', ['code' => 'PROGRAM 326', 'name' => 'PROGRAM ST SPECIAL DEALER MEI 2026']);
        $this->assertEquals($initialCount + 1, MasterProgram::count());
    }
}

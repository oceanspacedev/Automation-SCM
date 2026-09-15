<?php

namespace Tests\Feature;

use App\Models\ProgramSubmission;
use App\Models\User;
use App\Services\ProgramSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_program_submissions_with_filtering(): void
    {
        $user = User::factory()->create();

        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11 2021 Series',
            'sales_name' => 'M RISWAN',
            'credit_note_url' => 'https://drive.google.com/open?id=123',
            'agreement_url' => 'https://drive.google.com/open?id=456',
            'tax_invoice_url' => 'https://drive.google.com/open?id=789',
            'row_hash' => 'hash_test_1',
        ]);

        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 14:02:11',
            'region' => 'BIG BANDUNG',
            'id_real' => 'IDME00575',
            'dealer_name' => 'Samudra Komunika',
            'program_name' => 'Refund Realme 9 4G Series',
            'sales_name' => 'SANDY ARJAYAN',
            'row_hash' => 'hash_test_2',
        ]);

        // 1. Fetch all
        $response = $this->actingAs($user)->getJson('/api/program-submissions');
        $response->assertStatus(200);
        $response->assertJsonPath('total_submissions', 2);
        $this->assertCount(2, $response->json('submissions.data'));

        // 2. Filter by region
        $response = $this->actingAs($user)->getJson('/api/program-submissions?region=BIG+KARAWANG');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('submissions.data'));
        $this->assertEquals('Abadi Cell', $response->json('submissions.data.0.dealer_name'));

        // 3. Search by dealer name
        $response = $this->actingAs($user)->getJson('/api/program-submissions?search=Samudra');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('submissions.data'));
        $this->assertEquals('Samudra Komunika', $response->json('submissions.data.0.dealer_name'));
    }

    public function test_can_receive_webhook_from_google_form(): void
    {
        $payload = [
            'namedValues' => [
                'Timestamp' => ['10/10/2022 12:48:07'],
                'REGION' => ['BIG KARAWANG'],
                'ID REAL' => ['IDME00567'],
                'NAMA DEALER' => ['K Cell'],
                'NAMA PROGRAM' => ['Refund C30 Series Periode 16'],
                'NAMA SALES' => ['M RISWAN'],
                'DOKUMEN CREDIT NOTE' => ['https://drive.google.com/open?id=test_cn'],
                'AGREEMENT' => ['https://drive.google.com/open?id=test_agr'],
                'FAKTUR PAJAK' => ['https://drive.google.com/open?id=test_tax'],
            ],
        ];

        $response = $this->postJson('/api/webhooks/form-program', $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('program_submissions', [
            'id_real' => 'IDME00567',
            'dealer_name' => 'K Cell',
            'region' => 'BIG KARAWANG',
        ]);
    }

    public function test_service_can_process_sheet_rows(): void
    {
        $service = app(ProgramSubmissionService::class);

        $rows = [
            ['Timestamp', 'REGION', 'ID REAL', 'NAMA DEALER', 'NAMA PROGRAM', 'NAMA SALES', 'DOKUMEN CREDIT NOTE', 'AGREEMENT', 'FAKTUR PAJAK'],
            ['10/10/2022 12:35:41', 'BIG KARAWANG', 'IDME00652', 'Abadi Cell', 'Program SO C11', 'M RISWAN', 'https://drive.google.com/cn1', 'https://drive.google.com/agr1', 'https://drive.google.com/tax1'],
            ['10/10/2022 12:38:05', 'BIG KARAWANG', 'IDME01412', 'DMS Cell', 'Refund C30', 'M RISWAN', 'https://drive.google.com/cn2', 'https://drive.google.com/agr2', null],
        ];

        $result = $service->processSheetRows($rows);

        $this->assertEquals(2, $result['total_rows']);
        $this->assertEquals(2, $result['new_count']);
        $this->assertDatabaseCount('program_submissions', 2);

        $first = ProgramSubmission::where('id_real', 'IDME00652')->first();
        $this->assertNotNull($first);
        $this->assertEquals('Abadi Cell', $first->dealer_name);
        $this->assertEquals('https://drive.google.com/cn1', $first->credit_note_url);
    }

    public function test_can_save_webapp_url_config(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/program-submissions/config', [
            'url' => 'https://script.google.com/macros/s/test_token/exec',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $service = app(ProgramSubmissionService::class);
        $this->assertEquals('https://script.google.com/macros/s/test_token/exec', $service->getWebAppUrl());
    }
}

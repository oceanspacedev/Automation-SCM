<?php

namespace Tests\Feature;

use App\Console\Commands\AutoAnalyzeProgramAiCommand;
use App\Models\ProgramSubmission;
use App\Models\User;
use App\Services\DocumentAnalysisService;
use App\Services\ProgramSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

        // 4. Check programs dropdown list in response
        $this->assertContains('Program SO C11 2021 Series', $response->json('programs'));
        $this->assertContains('Refund Realme 9 4G Series', $response->json('programs'));

        // 5. Filter by program name
        $response = $this->actingAs($user)->getJson('/api/program-submissions?program='.urlencode('Program SO C11 2021 Series'));
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('submissions.data'));
        $this->assertEquals('Program SO C11 2021 Series', $response->json('submissions.data.0.program_name'));
        $this->assertEquals('Abadi Cell', $response->json('submissions.data.0.dealer_name'));
    }

    public function test_can_receive_webhook_from_google_form(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'is_complete' => true,
                                'cek_dokumen' => 'LENGKAP',
                                'status_potong_purchase' => 'BISA DI POTONG',
                                'keterangan' => 'Semua dokumen lengkap',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

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
            'cek_dokumen' => 'LENGKAP',
            'status_potong_purchase' => 'BISA DI POTONG',
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

    public function test_can_update_manual_tracking_columns_and_status_potong(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11 2021 Series',
            'sales_name' => 'M RISWAN',
            'row_hash' => 'hash_test_update',
        ]);

        $payload = [
            'no_po_sj' => 'PO/2026/09/001',
            'no_transaksi' => 'TRX-998822',
            'tgl_input' => '16/09/2026',
            'tgl_share_cn' => '16/09/2026',
            'lama_pending' => '2',
            'keterangan' => 'Menunggu faktur pajak',
            'cek_dokumen' => 'AGR SUDAH ADA',
            'status_potong_purchase' => 'SUDAH POTONG',
            'status_potong_ar' => 'DONE',
            'tgl_potong_tf' => '16/09/2026',
        ];

        $response = $this->actingAs($user)->patchJson("/api/program-submissions/{$submission->id}", $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('program_submissions', [
            'id' => $submission->id,
            'no_po_sj' => 'PO/2026/09/001',
            'no_transaksi' => 'TRX-998822',
            'status_potong_purchase' => 'SUDAH POTONG',
            'cek_dokumen' => 'AGR SUDAH ADA',
        ]);
    }

    public function test_can_filter_by_status_purchase(): void
    {
        $user = User::factory()->create();

        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'dealer_name' => 'Toko 1',
            'status_potong_purchase' => 'BELUM BISA POTONG',
            'row_hash' => 'hash_f_1',
        ]);

        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'dealer_name' => 'Toko 2',
            'status_potong_purchase' => 'SUDAH POTONG',
            'row_hash' => 'hash_f_2',
        ]);

        $response = $this->actingAs($user)->getJson('/api/program-submissions?status_purchase=SUDAH+POTONG');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('submissions.data'));
        $this->assertEquals('Toko 2', $response->json('submissions.data.0.dealer_name'));
    }

    public function test_can_filter_by_keterangan(): void
    {
        $user = User::factory()->create();

        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'dealer_name' => 'Toko 30 Plus',
            'keterangan' => 'LEBIH DARI 30 HARI',
            'row_hash' => 'hash_k_1',
        ]);

        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'dealer_name' => 'Toko 30 Minus',
            'keterangan' => 'KURANG DARI 30 HARI',
            'row_hash' => 'hash_k_2',
        ]);

        $response = $this->actingAs($user)->getJson('/api/program-submissions?keterangan=LEBIH+DARI+30+HARI');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('submissions.data'));
        $this->assertEquals('Toko 30 Plus', $response->json('submissions.data.0.dealer_name'));
        $this->assertContains('LEBIH DARI 30 HARI', $response->json('keterangan_options'));
    }

    public function test_can_analyze_submission_with_ai(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'dealer_name' => 'Complete Dealer Cell',
            'credit_note_url' => 'https://drive.google.com/cn1',
            'agreement_url' => 'https://drive.google.com/agr1',
            'tax_invoice_url' => 'https://drive.google.com/faktur1',
            'row_hash' => 'hash_complete_test',
        ]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'id' => 'chatcmpl-test-123',
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'is_complete' => true,
                                'cek_dokumen' => 'LENGKAP',
                                'status_potong_purchase' => 'BISA DI POTONG',
                                'keterangan' => 'Semua dokumen (Credit Note, Agreement, dan Faktur Pajak) lengkap dan siap diproses potong.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/program-submissions/{$submission->id}/analyze-ai");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'cek_dokumen' => 'LENGKAP',
                'status_potong_purchase' => 'BISA DI POTONG',
            ],
        ]);

        $this->assertDatabaseHas('program_submissions', [
            'id' => $submission->id,
            'cek_dokumen' => 'LENGKAP',
            'status_potong_purchase' => 'BISA DI POTONG',
        ]);
    }

    public function test_can_analyze_submission_with_missing_docs_ai(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'PT Incomplete Dealer',
            'ppn' => 50000,
            'credit_note_url' => 'https://drive.google.com/cn1',
            'agreement_url' => '',
            'tax_invoice_url' => '',
            'row_hash' => 'hash_incomplete_test',
        ]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'id' => 'chatcmpl-test-456',
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'is_complete' => false,
                                'cek_dokumen' => 'AGR & FAKTUR BELUM ADA',
                                'status_potong_purchase' => 'BELUM BISA POTONG',
                                'keterangan' => 'Dokumen Agreement dan Faktur Pajak belum diunggah.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/program-submissions/{$submission->id}/analyze-ai");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'cek_dokumen' => 'AGR & FAKTUR BELUM ADA',
                'status_potong_purchase' => 'BELUM BISA POTONG',
            ],
        ]);

        $this->assertDatabaseHas('program_submissions', [
            'id' => $submission->id,
            'cek_dokumen' => 'AGR & FAKTUR BELUM ADA',
            'status_potong_purchase' => 'BELUM BISA POTONG',
        ]);
    }

    public function test_can_get_and_save_ai_config(): void
    {
        $user = User::factory()->create();

        $saveResponse = $this->actingAs($user)->postJson('/api/program-submissions/ai-config', [
            'base_url' => 'https://router.rizqis.com/v1',
            'api_key' => 'sk-test-key-1234567890',
            'model' => 'ag/gemini-3.7-flash-low',
        ]);

        $saveResponse->assertStatus(200);
        $saveResponse->assertJson(['success' => true]);

        $getResponse = $this->actingAs($user)->getJson('/api/program-submissions/ai-config');
        $getResponse->assertStatus(200);
        $getResponse->assertJson([
            'config' => [
                'base_url' => 'https://router.rizqis.com/v1',
                'model' => 'ag/gemini-3.7-flash-low',
                'has_key' => true,
            ],
        ]);
    }

    public function test_can_get_ai_status_with_running_indicator(): void
    {
        $user = User::factory()->create();

        Cache::put(AutoAnalyzeProgramAiCommand::CACHE_KEY_STATUS, [
            'is_running' => true,
            'started_at' => now()->toIso8601String(),
            'total' => 10,
            'processed' => 3,
            'success' => 3,
            'failed' => 0,
            'current_dealer' => 'Abadi Cell',
            'last_heartbeat' => time(),
        ], 300);

        $response = $this->actingAs($user)->getJson('/api/program-submissions/ai-status');
        $response->assertStatus(200);
        $response->assertJson([
            'is_running' => true,
            'running_info' => [
                'is_running' => true,
                'total' => 10,
                'processed' => 3,
                'current_dealer' => 'Abadi Cell',
            ],
        ]);
    }

    public function test_can_export_program_submissions_to_excel(): void
    {
        $user = User::factory()->create();

        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11 2021 Series',
            'sales_name' => 'M RISWAN',
            'row_hash' => 'hash_test_export_1',
        ]);

        $response = $this->actingAs($user)->get('/api/program-submissions/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename="form_program_', (string) $response->headers->get('content-disposition'));
    }

    public function test_can_send_wa_notification_to_ar(): void
    {
        $user = User::factory()->create();

        config([
            'services.wag.token' => 'fake-test-token',
            'services.wag.ar_phone' => '081224290502',
        ]);

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11 2021 Series',
            'sales_name' => 'M RISWAN',
            'status_potong_purchase' => 'BISA DI POTONG',
            'row_hash' => 'hash_test_wa_ar',
        ]);

        Http::fake([
            '*/api/v1/messages' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'msg-12345',
                    'provider_message_id' => 'wamid-12345',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/program-submissions/{$submission->id}/send-wa-ar");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/messages') &&
                $request['recipient']['value'] === '6281224290502' &&
                str_contains($request['message']['text'], 'UNTUK TIM AR') &&
                str_contains($request['message']['text'], '/p/confirm/');
        });
    }

    public function test_can_send_wa_notification_to_telemarketing(): void
    {
        config([
            'services.wag.token' => 'fake-test-token',
            'services.wag.telemarketing_phone' => '081234567890',
        ]);

        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11 2021 Series',
            'status_potong_purchase' => 'BISA DI POTONG',
            'net_pay' => 439189,
            'row_hash' => 'hash_test_send_wa_tm',
        ]);

        Http::fake([
            'https://waghub.mekayastudio.com/api/v1/messages' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'msg-tm-12345',
                    'provider_message_id' => 'wamid-tm-12345',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/program-submissions/{$submission->id}/send-wa-telemarketing");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/messages') &&
                $request['recipient']['value'] === '6281234567890' &&
                str_contains($request['message']['text'], 'INFO TELEMARKETING') &&
                str_contains($request['message']['text'], 'BISA DI POTONG') &&
                str_contains($request['message']['text'], '439.189') &&
                str_contains($request['message']['text'], 'role=telemarketing') &&
                str_contains($request['message']['text'], '/p/confirm/');
        });
    }

    public function test_telemarketing_can_confirm_dealer_setuju_via_signed_url(): void
    {
        config([
            'services.wag.token' => 'fake-token-test',
            'services.wag.ar_phone' => '081224290502',
        ]);

        Http::fake([
            'https://waghub.mekayastudio.com/api/v1/messages' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'msg-ar-forward-123',
                    'provider_message_id' => 'wamid-ar-123',
                ],
            ], 201),
        ]);

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11 2021 Series',
            'status_potong_purchase' => 'BISA DI POTONG',
            'net_pay' => 500000,
            'row_hash' => 'hash_test_confirm_tm_setuju',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'program-submissions.confirm',
            now()->addDays(7),
            ['id' => $submission->id, 'role' => 'telemarketing']
        );

        // 1. GET request should display the interactive page with "Iya" and "Tidak" buttons, WITHOUT modifying the database
        $getResponse = $this->get($signedUrl);
        $getResponse->assertStatus(200);
        $getResponse->assertSee('Konfirmasi Klaim Program');
        $getResponse->assertSee('Iya, Dealer Setuju (Ajukan ke AR)');
        $getResponse->assertSee('Tidak / Tunda (Dealer Belum Order)');

        $this->assertDatabaseHas('program_submissions', [
            'id' => $submission->id,
            'status_potong_ar' => null,
        ]);

        // 2. POST request with action=setuju simulates clicking the "Iya" button
        $postResponse = $this->post($signedUrl, [
            'action' => 'setuju',
        ]);
        $postResponse->assertStatus(200);
        $postResponse->assertSee('Dealer Setuju Dipotong!');
        $postResponse->assertSee('STATUS AR: DEALER SETUJU (PROSES AR)');
        $postResponse->assertSee('WhatsApp Berhasil Terkirim ke AR');

        $this->assertDatabaseHas('program_submissions', [
            'id' => $submission->id,
            'status_potong_ar' => 'DEALER SETUJU (PROSES AR)',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/messages') &&
                $request['recipient']['value'] === '6281224290502' &&
                str_contains($request['message']['text'], 'UNTUK TIM AR') &&
                str_contains($request['message']['text'], 'role=ar') &&
                str_contains($request['message']['text'], '500.000');
        });
    }

    public function test_ar_can_confirm_potong_via_signed_url(): void
    {
        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11 2021 Series',
            'status_potong_purchase' => 'BISA DI POTONG',
            'row_hash' => 'hash_test_confirm_1',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'program-submissions.confirm',
            now()->addDays(7),
            ['id' => $submission->id, 'role' => 'ar']
        );

        // 1. GET request should display the interactive page without updating database
        $getResponse = $this->get($signedUrl);
        $getResponse->assertStatus(200);
        $getResponse->assertSee('Konfirmasi Pemotongan Klaim');
        $getResponse->assertSee('Iya, Sudah Dipotong (Selesai)');

        $this->assertDatabaseHas('program_submissions', [
            'id' => $submission->id,
            'status_potong_purchase' => 'BISA DI POTONG',
        ]);

        // 2. POST request with action=potong executes the update
        $postResponse = $this->post($signedUrl, [
            'action' => 'potong',
        ]);
        $postResponse->assertStatus(200);
        $postResponse->assertSee('Konfirmasi Berhasil!');
        $postResponse->assertSee('STATUS: SUDAH POTONG');

        $this->assertDatabaseHas('program_submissions', [
            'id' => $submission->id,
            'status_potong_purchase' => 'SUDAH POTONG',
            'status_potong_ar' => 'DONE',
            'tgl_potong_tf' => date('n/j/Y'),
        ]);
    }

    public function test_cannot_confirm_with_invalid_signature(): void
    {
        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:32:55',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'status_potong_purchase' => 'BISA DI POTONG',
            'row_hash' => 'hash_test_invalid_sig',
        ]);

        // Access without valid signature
        $response = $this->get("/p/confirm/{$submission->id}?action=potong&signature=invalid_hash");
        $response->assertStatus(403);
    }

    public function test_can_update_and_export_financial_and_tax_audit_columns(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2026 10:00:00',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00999',
            'dealer_name' => 'Mitra Komunika',
            'program_name' => 'Cashback Realme 12',
            'credit_note_url' => 'https://drive.google.com/open?id=cn123',
            'agreement_url' => 'https://drive.google.com/open?id=agr123',
            'tax_invoice_url' => 'https://drive.google.com/open?id=tax123',
            'row_hash' => 'hash_fin_test_1',
        ]);

        // 1. Update financial columns
        $updatePayload = [
            'incentive' => 700000,
            'dpp' => 630631,
            'dpp_lain' => 0,
            'ppn' => 0,
            'nilai_pph' => 15766,
            'net_pay' => 614865,
            'cek_pajak_tarif_pph' => 14640,
            'note_pph' => 'CAP?',
            'no_faktur' => '010.000-24.12345678',
            'tgl_faktur' => '15/05/2026',
        ];

        $res = $this->actingAs($user)->patchJson("/api/program-submissions/{$submission->id}", $updatePayload);
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseHas('program_submissions', [
            'id' => $submission->id,
            'incentive' => 700000,
            'dpp' => 630631,
            'net_pay' => 614865,
            'selisih' => 1126.00, // 15766 - 14640
            'note_pph' => 'CAP?',
            'no_faktur' => '010.000-24.12345678',
            'tgl_faktur' => '15/05/2026',
        ]);

        // 2. Fetch list and assert columns returned
        $listRes = $this->actingAs($user)->getJson('/api/program-submissions');
        $listRes->assertStatus(200);
        $listRes->assertJsonFragment([
            'id' => $submission->id,
            'incentive' => 700000,
            'dpp' => 630631,
            'note_pph' => 'CAP?',
            'no_faktur' => '010.000-24.12345678',
        ]);

        // 3. Export Excel
        $exportRes = $this->actingAs($user)->get('/api/program-submissions/export');
        $exportRes->assertStatus(200);
        $this->assertTrue(str_contains($exportRes->headers->get('content-disposition'), '.xlsx'));
    }

    public function test_non_pkp_reconstructs_gross_incentive_and_net_pay(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2026 10:00:00',
            'region' => 'CIREBON',
            'id_real' => 'B100969',
            'dealer_name' => 'LESTARI CELL CIGASONG',
            'program_name' => 'PROGRAM DSA AGUSTUS 2026',
            'sales_name' => 'AAB ABDURAHMAN',
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr',
            'tax_invoice_url' => null,
            'row_hash' => 'hash_lestari_test',
        ]);

        // Fake AI Router returning DPP 450450 from CN
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'is_complete' => false,
                                'cek_dokumen' => 'FAKTUR BELUM ADA',
                                'status_potong_purchase' => 'BELUM BISA POTONG',
                                'keterangan' => 'Faktur belum ada',
                                'dpp' => 450450,
                                'nilai_pph' => 11261,
                                'has_stamp' => true,
                                'has_signature' => false,
                                'has_npwp' => true,
                                'note_pph' => 'TTD?',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        Cache::put('ai_router_config', [
            'base_url' => 'https://router.example.com/v1',
            'api_key' => 'test-key',
            'model' => 'test-model',
        ]);

        $response = $this->actingAs($user)->postJson("/api/program-submissions/{$submission->id}/analyze-ai");
        $response->assertStatus(200);

        $submission->refresh();

        // 450.450 * 1.11 = 499.999,5 rounded to thousands => 500.000
        $this->assertEquals(500000.0, $submission->incentive);
        $this->assertEquals(450450.0, $submission->dpp);
        $this->assertEquals(11261.0, $submission->nilai_pph);
        // Net pay: 450450 - 11261 = 439189
        $this->assertEquals(439189.0, $submission->net_pay);
        // Cek pajak diset 0.0 karena verifikasi pajak dilakukan oleh tim pajak internal
        $this->assertEquals(0.0, $submission->cek_pajak_tarif_pph);
        $this->assertEquals(0.0, $submission->selisih);
        $this->assertEquals('TTD?', $submission->note_pph);
    }

    public function test_detects_swapped_and_invalid_documents_and_locks_status(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2026 10:00:00',
            'region' => 'CIREBON',
            'id_real' => 'B100970',
            'dealer_name' => 'BERKAH CELL',
            'program_name' => 'PROGRAM DSA AGUSTUS 2026',
            'sales_name' => 'AAB ABDURAHMAN',
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr',
            'tax_invoice_url' => 'https://drive.google.com/open?id=test_tax',
            'row_hash' => 'hash_swapped_test',
        ]);

        // Fake AI Router returning swapped documents (CN has Agreement, Agr has CN)
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'is_complete' => true,
                                'cek_dokumen' => 'LENGKAP',
                                'status_potong_purchase' => 'BISA DI POTONG',
                                'keterangan' => 'Semua dokumen ada tapi tertukar',
                                'dpp' => 500000,
                                'nilai_pph' => 12500,
                                'doc_validation' => [
                                    'cn' => [
                                        'status' => 'swapped',
                                        'actual_type' => 'agr',
                                        'message' => 'File di kolom CN adalah dokumen Agreement',
                                    ],
                                    'agr' => [
                                        'status' => 'swapped',
                                        'actual_type' => 'cn',
                                        'message' => 'File di kolom Agr adalah dokumen Credit Note',
                                    ],
                                    'faktur' => [
                                        'status' => 'valid',
                                        'actual_type' => 'faktur',
                                        'message' => 'Valid Faktur Pajak',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        Cache::put('ai_router_config', [
            'base_url' => 'https://router.example.com/v1',
            'api_key' => 'test-key',
            'model' => 'test-model',
        ]);

        $response = $this->actingAs($user)->postJson("/api/program-submissions/{$submission->id}/analyze-ai");
        $response->assertStatus(200);

        $submission->refresh();

        // Must lock status to BELUM BISA POTONG due to swapped documents
        $this->assertEquals('BELUM BISA POTONG', $submission->status_potong_purchase);
        $this->assertStringContainsString('TERTUKAR', $submission->cek_dokumen);
        $this->assertIsArray($submission->doc_validation);
        $this->assertEquals('swapped', $submission->doc_validation['cn']['status']);
        $this->assertEquals('agr', $submission->doc_validation['cn']['actual_type']);
        $this->assertEquals('swapped', $submission->doc_validation['agr']['status']);
        $this->assertEquals('valid', $submission->doc_validation['faktur']['status']);
    }

    public function test_missing_faktur_marked_as_faktur_belum_ada_not_tidak_sesuai(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2026 10:00:00',
            'region' => 'CIREBON',
            'id_real' => 'B100971',
            'dealer_name' => 'PT MANDIRI CELL PKP',
            'program_name' => 'PROGRAM DSA AGUSTUS 2026',
            'sales_name' => 'AAB ABDURAHMAN',
            'ppn' => 100000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr',
            'tax_invoice_url' => '',
            'row_hash' => 'hash_empty_faktur_test',
        ]);

        // Even if AI mistakenly returned 'invalid' for empty faktur
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'is_complete' => false,
                                'cek_dokumen' => 'DOKUMEN TIDAK SESUAI (FAKTUR)',
                                'status_potong_purchase' => 'BELUM BISA POTONG',
                                'keterangan' => 'Faktur tidak diunggah',
                                'doc_validation' => [
                                    'cn' => ['status' => 'valid', 'actual_type' => 'cn', 'message' => 'CN valid'],
                                    'agr' => ['status' => 'valid', 'actual_type' => 'agr', 'message' => 'Agr valid'],
                                    'faktur' => ['status' => 'invalid', 'actual_type' => 'other', 'message' => 'Faktur kosong'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        Cache::put('ai_router_config', [
            'base_url' => 'https://router.example.com/v1',
            'api_key' => 'test-key',
            'model' => 'test-model',
        ]);

        $response = $this->actingAs($user)->postJson("/api/program-submissions/{$submission->id}/analyze-ai");
        $response->assertStatus(200);

        $submission->refresh();

        // Must be FAKTUR BELUM ADA, NOT DOKUMEN TIDAK SESUAI
        $this->assertEquals('FAKTUR BELUM ADA', $submission->cek_dokumen);
        $this->assertEquals('BELUM BISA POTONG', $submission->status_potong_purchase);
        $this->assertEquals('empty', $submission->doc_validation['faktur']['status']);
    }

    public function test_sync_deletes_submissions_removed_from_spreadsheet(): void
    {
        $service = app(ProgramSubmissionService::class);

        // Pre-create 2 submissions
        $sub1 = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:35:41',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11',
            'row_hash' => sha1('10/10/2022 12:35:41|BIG KARAWANG|IDME00652|Abadi Cell|Program SO C11'),
        ]);

        $sub2 = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:38:05',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME01412',
            'dealer_name' => 'DMS Cell',
            'program_name' => 'Refund C30',
            'row_hash' => sha1('10/10/2022 12:38:05|BIG KARAWANG|IDME01412|DMS Cell|Refund C30'),
        ]);

        // Sheet now only contains Abadi Cell (DMS Cell was deleted in spreadsheet)
        $rows = [
            ['Timestamp', 'REGION', 'ID REAL', 'NAMA DEALER', 'NAMA PROGRAM', 'NAMA SALES', 'DOKUMEN CREDIT NOTE', 'AGREEMENT', 'FAKTUR PAJAK'],
            ['10/10/2022 12:35:41', 'BIG KARAWANG', 'IDME00652', 'Abadi Cell', 'Program SO C11', 'M RISWAN', 'https://drive.google.com/cn1', 'https://drive.google.com/agr1', 'https://drive.google.com/tax1'],
        ];

        $result = $service->processSheetRows($rows, 0);

        $this->assertEquals(1, $result['total_rows']);
        $this->assertEquals(1, $result['deleted_count']);
        $this->assertDatabaseHas('program_submissions', ['id' => $sub1->id]);
        $this->assertDatabaseMissing('program_submissions', ['id' => $sub2->id]);
    }

    public function test_service_imports_submissions_with_empty_dealer_name_if_documents_exist(): void
    {
        $service = app(ProgramSubmissionService::class);

        $rows = [
            ['Timestamp', 'REGION', 'ID REAL', 'NAMA DEALER', 'NAMA PROGRAM', 'NAMA SALES', 'DOKUMEN CREDIT NOTE', 'AGREEMENT', 'FAKTUR PAJAK'],
            ['10/10/2022 12:35:41', 'BIG KARAWANG', '', '', '', 'M RISWAN', 'https://drive.google.com/cn_anon', 'https://drive.google.com/agr_anon', null],
        ];

        $result = $service->processSheetRows($rows, 0);

        $this->assertEquals(1, $result['total_rows']);
        $this->assertEquals(1, $result['new_count']);

        $submission = ProgramSubmission::where('sales_name', 'M RISWAN')->first();
        $this->assertNotNull($submission);
        $this->assertEquals('[Belum Ada Nama Dealer]', $submission->dealer_name);
        $this->assertEquals('[Belum Ada Nama Program]', $submission->program_name);
        $this->assertEquals('https://drive.google.com/cn_anon', $submission->credit_note_url);
    }

    public function test_can_update_dealer_name_and_document_urls_and_sets_manual_edit_flag(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:35:41',
            'region' => 'BIG KARAWANG',
            'id_real' => '',
            'dealer_name' => '[Belum Ada Nama Dealer]',
            'program_name' => 'Program C11',
            'credit_note_url' => 'https://drive.google.com/cn_old',
            'row_hash' => 'hash_test_manual_1',
            'is_manual_edit' => false,
        ]);

        $payload = [
            'dealer_name' => 'Bintang Terang Cell',
            'id_real' => 'IDME00999',
            'credit_note_url' => 'https://drive.google.com/cn_new',
        ];

        $response = $this->actingAs($user)->patchJson("/api/program-submissions/{$submission->id}", $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $submission->refresh();
        $this->assertEquals('Bintang Terang Cell', $submission->dealer_name);
        $this->assertEquals('IDME00999', $submission->id_real);
        $this->assertEquals('https://drive.google.com/cn_new', $submission->credit_note_url);
        $this->assertTrue($submission->is_manual_edit);
    }

    public function test_sync_preserves_admin_manual_edits_from_spreadsheet_overwrite(): void
    {
        $service = app(ProgramSubmissionService::class);

        $rowHash = sha1('10/10/2022 12:35:41|BIG KARAWANG|IDME00652|Abadi Cell|Program SO C11');

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:35:41',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652_FIXED',
            'dealer_name' => 'Abadi Cell (Nama Benar Hasil Edit Admin)',
            'program_name' => 'Program SO C11 (Diperbaiki)',
            'credit_note_url' => 'https://drive.google.com/cn_fixed',
            'row_hash' => $rowHash,
            'is_manual_edit' => true,
        ]);

        // Incoming sheet row still has the old/broken data
        $rows = [
            ['Timestamp', 'REGION', 'ID REAL', 'NAMA DEALER', 'NAMA PROGRAM', 'NAMA SALES', 'DOKUMEN CREDIT NOTE', 'AGREEMENT', 'FAKTUR PAJAK'],
            ['10/10/2022 12:35:41', 'BIG KARAWANG', 'IDME00652', 'Abadi Cell', 'Program SO C11', 'M RISWAN', 'https://drive.google.com/cn_broken', 'https://drive.google.com/agr1', 'https://drive.google.com/tax1'],
        ];

        $service->processSheetRows($rows, 0);

        $submission->refresh();
        // Admin's manual corrections MUST NOT be overwritten by the sheet sync
        $this->assertEquals('Abadi Cell (Nama Benar Hasil Edit Admin)', $submission->dealer_name);
        $this->assertEquals('IDME00652_FIXED', $submission->id_real);
        $this->assertEquals('Program SO C11 (Diperbaiki)', $submission->program_name);
        $this->assertEquals('https://drive.google.com/cn_fixed', $submission->credit_note_url);
    }

    public function test_can_delete_submission_and_sync_does_not_resurrect_it(): void
    {
        $user = User::factory()->create();
        $service = app(ProgramSubmissionService::class);

        $rowHash = sha1('10/10/2022 12:35:41|BIG KARAWANG|IDME00652|Spam Toko|Program SO C11');

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:35:41',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Spam Toko',
            'program_name' => 'Program SO C11',
            'row_hash' => $rowHash,
        ]);

        // 1. Delete via API
        $response = $this->actingAs($user)->deleteJson("/api/program-submissions/{$submission->id}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertSoftDeleted('program_submissions', ['id' => $submission->id]);

        // 2. Run sync where spreadsheet still contains the deleted spam row
        $rows = [
            ['Timestamp', 'REGION', 'ID REAL', 'NAMA DEALER', 'NAMA PROGRAM', 'NAMA SALES', 'DOKUMEN CREDIT NOTE', 'AGREEMENT', 'FAKTUR PAJAK'],
            ['10/10/2022 12:35:41', 'BIG KARAWANG', 'IDME00652', 'Spam Toko', 'Program SO C11', 'M RISWAN', 'https://drive.google.com/cn1', 'https://drive.google.com/agr1', 'https://drive.google.com/tax1'],
        ];

        $service->processSheetRows($rows, 0);

        // It should still be soft-deleted, not resurrected in active submissions!
        $this->assertSoftDeleted('program_submissions', ['id' => $submission->id]);
        $this->assertEquals(0, ProgramSubmission::count());
    }

    public function test_can_swap_swapped_documents(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:35:41',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11',
            'credit_note_url' => 'https://drive.google.com/cn_url',
            'agreement_url' => 'https://drive.google.com/faktur_in_agr_col',
            'tax_invoice_url' => 'https://drive.google.com/agr_in_faktur_col',
            'doc_validation' => [
                'agr' => ['status' => 'swapped', 'detected_type' => 'faktur', 'message' => 'File di kolom Agr terdeteksi berisi Faktur Pajak'],
                'faktur' => ['status' => 'swapped', 'detected_type' => 'agr', 'message' => 'File di kolom Faktur terdeteksi berisi Agreement'],
            ],
            'cek_dokumen' => 'DOKUMEN TIDAK SESUAI',
            'status_potong_purchase' => 'BELUM BISA POTONG',
            'row_hash' => 'hash_test_swap_1',
        ]);

        $response = $this->actingAs($user)->postJson("/api/program-submissions/{$submission->id}/swap-docs", [
            'type' => 'agr_faktur',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $submission->refresh();
        $this->assertEquals('https://drive.google.com/agr_in_faktur_col', $submission->agreement_url);
        $this->assertEquals('https://drive.google.com/faktur_in_agr_col', $submission->tax_invoice_url);
        $this->assertEquals('valid', $submission->doc_validation['agr']['status']);
        $this->assertEquals('valid', $submission->doc_validation['faktur']['status']);
        $this->assertEquals('LENGKAP', $submission->cek_dokumen);
        $this->assertEquals('BISA DI POTONG', $submission->status_potong_purchase);
        $this->assertTrue($submission->is_manual_edit);
    }

    public function test_can_update_whatsapp_number_on_program_submission(): void
    {
        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:35:41',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11',
            'row_hash' => 'hash_test_update_wa',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/program-submissions/{$submission->id}", [
            'dealer_name' => 'Abadi Cell',
            'program_name' => 'Program SO C11',
            'whatsapp' => '081224290502',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $submission->refresh();
        $this->assertEquals('081224290502', $submission->whatsapp);
        $this->assertEquals('081224290502', $submission->effective_whatsapp);
    }

    public function test_can_send_wa_doc_error_notification(): void
    {
        config([
            'services.wag.token' => 'fake-test-token',
        ]);

        $user = User::factory()->create();

        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2022 12:35:41',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'CV. PRIMA PRESTASI ABADI',
            'program_name' => 'PROGRAM PROMOTION NOTE 80',
            'sales_name' => 'MUHAMMAD RISWAN',
            'whatsapp' => '081224290502',
            'credit_note_url' => 'https://drive.google.com/cn_ok',
            'agreement_url' => 'https://drive.google.com/agr_salah',
            'doc_validation' => [
                'cn' => ['status' => 'valid'],
                'agr' => [
                    'status' => 'invalid',
                    'message' => 'Dokumen Agreement tidak sesuai atau salah file',
                    'detected_type' => 'unknown',
                ],
            ],
            'row_hash' => 'hash_test_send_wa_doc_err',
        ]);

        Http::fake([
            'https://waghub.mekayastudio.com/api/v1/messages' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'msg-doc-err-12345',
                    'provider_message_id' => 'wamid-doc-err-12345',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/program-submissions/{$submission->id}/send-wa-doc-error", [
            'phone' => '081224290502',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'recipient_phone' => '6281224290502',
        ]);
        $this->assertNotEmpty($response->json('wa_url'));
        $this->assertStringContainsString('6281224290502', $response->json('wa_url'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/messages') &&
                $request['recipient']['value'] === '6281224290502' &&
                str_contains($request['message']['text'], 'perbaikan dokumen') &&
                str_contains($request['message']['text'], 'CV. PRIMA PRESTASI ABADI') &&
                str_contains($request['message']['text'], 'Agreement') &&
                str_contains($request['message']['text'], 'sesuaikan nama program dengan isi CN') &&
                str_contains($request['message']['text'], 'upload dokumen ulang yang sesuai') &&
                str_contains($request['message']['text'], 'Catatan Dokumen') &&
                str_contains($request['message']['text'], 'dipisah satu per satu') &&
                ! str_contains($request['message']['text'], '*');
        });
    }

    public function test_non_pkp_with_only_cn_and_agr_is_bisa_di_potong(): void
    {
        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2026 12:35:41',
            'region' => 'BIG CIREBON',
            'id_real' => 'NEWCO CELL',
            'dealer_name' => 'NEWCO CELL',
            'program_name' => 'PROGRAM PROMOTION NOTE 80 4+128 JUNI 2026',
            'sales_name' => 'AAB ABDURAHMAN',
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn_valid',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr_valid',
            'tax_invoice_url' => '',
            'ppn' => 0,
            'row_hash' => 'hash_test_non_pkp_two_docs',
        ]);

        $eval = app(DocumentAnalysisService::class)->evaluateCompleteness($submission);

        $this->assertTrue($eval['is_complete']);
        $this->assertEquals('BISA DI POTONG', $eval['status_potong_purchase']);
        $this->assertEquals('LENGKAP', $eval['cek_dokumen']);
    }

    public function test_pkp_with_only_cn_and_agr_cannot_be_cut_without_faktur(): void
    {
        $submission = ProgramSubmission::create([
            'submission_timestamp' => '10/10/2026 12:35:41',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'program_name' => 'PROGRAM PROMOTION NOTE 80 4+128 JUNI 2026',
            'sales_name' => 'MUHAMMAD RISWAN',
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn_valid',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr_valid',
            'tax_invoice_url' => '',
            'ppn' => 148649, // PKP because PPN > 0
            'row_hash' => 'hash_test_pkp_without_faktur',
        ]);

        $eval = app(DocumentAnalysisService::class)->evaluateCompleteness($submission);

        $this->assertFalse($eval['is_complete']);
        $this->assertEquals('BELUM BISA POTONG', $eval['status_potong_purchase']);
        $this->assertEquals('FAKTUR BELUM ADA', $eval['cek_dokumen']);
    }

    public function test_can_get_public_form_options(): void
    {
        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2026 12:35:41',
            'region' => 'BIG BANDUNG',
            'id_real' => 'IDME001',
            'dealer_name' => 'Dealer Bandung',
            'program_name' => 'PROGRAM DSA FEBRUARI 2026',
            'sales_name' => 'JEJE ROHIMAN',
            'row_hash' => 'hash_test_options_1',
        ]);

        $response = $this->getJson('/api/program-submissions/form-options');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'programs',
            'regions',
            'sales',
        ]);

        $this->assertContains('BIG BANDUNG', $response->json('regions'));
        $this->assertContains('PROGRAM DSA FEBRUARI 2026', $response->json('programs'));
        $this->assertContains('JEJE ROHIMAN', $response->json('sales'));
    }

    public function test_can_pre_validate_documents_and_detect_swap(): void
    {
        Storage::fake('public');

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'is_complete' => false,
                                'cek_dokumen' => 'DOKUMEN TERTUKAR (CN ↔ AGR)',
                                'status_potong_purchase' => 'BELUM BISA POTONG',
                                'keterangan' => 'Dokumen tertukar posisi upload.',
                                'doc_validation' => [
                                    'cn' => [
                                        'status' => 'swapped',
                                        'actual_type' => 'agr',
                                        'message' => 'File di slot Credit Note adalah dokumen Agreement',
                                    ],
                                    'agr' => [
                                        'status' => 'swapped',
                                        'actual_type' => 'cn',
                                        'message' => 'File di slot Agreement adalah dokumen Credit Note',
                                    ],
                                    'faktur' => [
                                        'status' => 'empty',
                                        'actual_type' => 'none',
                                        'message' => 'Faktur Pajak tidak diunggah',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $fakeAgrFileInCnSlot = UploadedFile::fake()->create('agreement_resmi_2026.pdf', 50, 'application/pdf');
        $fakeCnFileInAgrSlot = UploadedFile::fake()->create('credit_note_inv_123.pdf', 50, 'application/pdf');

        $response = $this->post('/api/program-submissions/pre-validate', [
            'program_name' => 'PROGRAM DSA FEBRUARI 2026',
            'region' => 'BIG KARAWANG',
            'id_real' => 'IDME00652',
            'dealer_name' => 'Abadi Cell',
            'sales_name' => 'M RISWAN',
            'credit_note_file' => $fakeAgrFileInCnSlot,
            'agreement_file' => $fakeCnFileInAgrSlot,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertTrue($response->json('data.has_swapped'));
        $this->assertEquals('swapped', $response->json('data.doc_validation.cn.status'));
        $this->assertEquals('swapped', $response->json('data.doc_validation.agr.status'));
        $this->assertEquals('BELUM BISA POTONG', $response->json('data.status_potong_purchase'));
    }

    public function test_can_submit_public_program_form(): void
    {
        Storage::fake('public');

        $cnFile = UploadedFile::fake()->create('credit_note_toko_jaya.pdf', 50, 'application/pdf');
        $agrFile = UploadedFile::fake()->create('agreement_toko_jaya.pdf', 50, 'application/pdf');

        $response = $this->postJson('/api/program-submissions/submit-form', [
            'program_name' => 'PROGRAM DSA FEBRUARI 2026',
            'region' => 'BIG BANDUNG',
            'id_real' => 'IDME00789',
            'dealer_name' => 'Toko Jaya Selular',
            'sales_name' => 'SANDY ARJAYAN',
            'whatsapp' => '081234567890',
            'credit_note_file' => $cnFile,
            'agreement_file' => $agrFile,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('program_submissions', [
            'dealer_name' => 'Toko Jaya Selular',
            'id_real' => 'IDME00789',
            'program_name' => 'PROGRAM DSA FEBRUARI 2026',
            'region' => 'BIG BANDUNG',
            'sales_name' => 'SANDY ARJAYAN',
            'whatsapp' => '081234567890',
        ]);

        $submission = ProgramSubmission::where('id_real', 'IDME00789')->first();
        $this->assertNotNull($submission);
        $this->assertTrue(
            str_contains($submission->credit_note_url, 'storage.completeselular.com') ||
            str_contains($submission->credit_note_url, 'storage/program_documents')
        );
        $this->assertTrue(
            str_contains($submission->agreement_url, 'storage.completeselular.com') ||
            str_contains($submission->agreement_url, 'storage/program_documents')
        );
        $this->assertStringContainsString('Toko Jaya Selular', $submission->credit_note_url);
        $this->assertStringContainsString('PROGRAM DSA FEBRUARI 2026', $submission->credit_note_url);
    }

    public function test_cannot_submit_public_program_form_with_swapped_or_invalid_documents(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://router.rizqis.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'is_complete' => false,
                                'cek_dokumen' => 'DOKUMEN TERTUKAR (CN (AGR), AGR (CN))',
                                'status_potong_purchase' => 'BELUM BISA POTONG',
                                'keterangan' => 'Dokumen tertukar.',
                                'doc_validation' => [
                                    'cn' => [
                                        'status' => 'swapped',
                                        'actual_type' => 'agr',
                                        'message' => 'File di slot Credit Note terdeteksi berisi Dokumen Agreement.',
                                    ],
                                    'agr' => [
                                        'status' => 'swapped',
                                        'actual_type' => 'cn',
                                        'message' => 'File di slot Agreement terdeteksi berisi Dokumen Credit Note.',
                                    ],
                                    'faktur' => [
                                        'status' => 'empty',
                                        'actual_type' => 'none',
                                        'message' => 'Faktur Pajak tidak diunggah',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        // CN slot contains Agreement, AGR slot contains CN
        $swappedCnFile = UploadedFile::fake()->create('agreement_toko_jaya.pdf', 50, 'application/pdf');
        $swappedAgrFile = UploadedFile::fake()->create('credit_note_toko_jaya.pdf', 50, 'application/pdf');

        $response = $this->post('/api/program-submissions/submit-form', [
            'program_name' => 'PROGRAM DSA FEBRUARI 2026',
            'region' => 'BIG BANDUNG',
            'id_real' => 'IDME00789',
            'dealer_name' => 'Toko Jaya Selular',
            'sales_name' => 'SANDY ARJAYAN',
            'whatsapp' => '081234567890',
            'credit_note_file' => $swappedCnFile,
            'agreement_file' => $swappedAgrFile,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertEquals('DOC_SWAPPED', $response->json('error_type'));

        // Ensure database does NOT contain this dirty submission
        $this->assertDatabaseMissing('program_submissions', [
            'dealer_name' => 'Toko Jaya Selular',
            'id_real' => 'IDME00789',
        ]);
    }

    public function test_can_filter_submissions_by_web_form_source(): void
    {
        $user = User::factory()->create();

        // 1. Spreadsheet source
        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2026 12:00:00',
            'region' => 'BIG BANDUNG',
            'id_real' => 'IDME_SHEET_1',
            'dealer_name' => 'Sheet Dealer',
            'program_name' => 'PROGRAM DSA FEBRUARI 2026',
            'sales_name' => 'SANDY',
            'row_hash' => 'hash_sheet_test_1',
            'raw_data' => null,
        ]);

        // 2. Web form source
        ProgramSubmission::create([
            'submission_timestamp' => '10/10/2026 12:30:00',
            'region' => 'BIG CIREBON',
            'id_real' => 'IDME_WEB_1',
            'dealer_name' => 'Web Form Dealer',
            'program_name' => 'PROGRAM DSA FEBRUARI 2026',
            'sales_name' => 'AAB',
            'row_hash' => 'hash_web_test_1',
            'raw_data' => [
                'source' => 'web_form',
            ],
        ]);

        $response = $this->actingAs($user)->getJson('/api/program-submissions?source=web_form');
        $response->assertStatus(200);

        $data = $response->json('submissions.data');
        $this->assertNotEmpty($data);
        $ids = array_column($data, 'id_real');
        $this->assertContains('IDME_WEB_1', $ids);
        $this->assertNotContains('IDME_SHEET_1', $ids);
        $this->assertGreaterThanOrEqual(1, $response->json('web_form_total'));
    }

    public function test_get_and_save_spreadsheet_config(): void
    {
        $getRes = $this->getJson('/api/program-submissions/config');
        $getRes->assertOk()
            ->assertJsonStructure(['webapp_url', 'spreadsheet_url']);

        $postRes = $this->postJson('/api/program-submissions/config', [
            'url' => 'https://script.google.com/macros/s/test_custom_token/exec',
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/1rDYiHsNR43H44g2xyV-igyp1Ijv_jp8v8FblAMHqof8/edit?gid=0#gid=0',
        ]);

        $postRes->assertOk()
            ->assertJson([
                'success' => true,
                'configured_webapp_url' => 'https://script.google.com/macros/s/test_custom_token/exec',
                'configured_spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/1rDYiHsNR43H44g2xyV-igyp1Ijv_jp8v8FblAMHqof8/edit?gid=0#gid=0',
            ]);
    }

    public function test_push_single_submission_to_spreadsheet(): void
    {
        Http::fake([
            'script.google.com/macros/s/*' => Http::response([
                'success' => true,
                'row' => 2,
            ], 200),
        ]);

        $sub = ProgramSubmission::create([
            'row_hash' => 'hash_test_push_sub',
            'submission_timestamp' => '02/10/2026 14:00:00',
            'region' => 'BIG BANDUNG',
            'id_real' => 'REAL-PUSH-1',
            'dealer_name' => 'DEALER PUSH TEST',
            'program_name' => 'PROGRAM REALME TEST',
            'sales_name' => 'SALES TEST',
            'whatsapp' => '081234567890',
            'credit_note_url' => 'https://example.com/cn.pdf',
            'agreement_url' => 'https://example.com/agr.pdf',
            'tax_invoice_url' => 'https://example.com/fp.pdf',
        ]);

        $res = $this->postJson("/api/program-submissions/{$sub->id}/push-spreadsheet");
        $res->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        Http::assertSent(function ($request) use ($sub) {
            $data = $request->data();

            return str_contains($request->url(), 'script.google.com/macros/s/') &&
                   $data['id_real'] === $sub->id_real &&
                   $data['dealer_name'] === $sub->dealer_name &&
                   count($data['row']) === 11;
        });
    }
}

<?php

namespace Tests\Feature;

use App\Console\Commands\AutoAnalyzeProgramAiCommand;
use App\Models\ProgramSubmission;
use App\Models\User;
use App\Services\ProgramSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            'dealer_name' => 'Incomplete Dealer Cell',
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
            'model' => 'ag/gemini-3-flash',
        ]);

        $saveResponse->assertStatus(200);
        $saveResponse->assertJson(['success' => true]);

        $getResponse = $this->actingAs($user)->getJson('/api/program-submissions/ai-config');
        $getResponse->assertStatus(200);
        $getResponse->assertJson([
            'config' => [
                'base_url' => 'https://router.rizqis.com/v1',
                'model' => 'ag/gemini-3-flash',
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
                str_contains($request['message']['text'], 'BISA DI POTONG') &&
                str_contains($request['message']['text'], '/p/confirm/');
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
            now()->addHours(1),
            ['id' => $submission->id, 'action' => 'potong']
        );

        $response = $this->get($signedUrl);
        $response->assertStatus(200);
        $response->assertSee('Konfirmasi Berhasil!');
        $response->assertSee('STATUS: SUDAH POTONG');

        $this->assertDatabaseHas('program_submissions', [
            'id' => $submission->id,
            'status_potong_purchase' => 'SUDAH POTONG',
            'status_potong_ar' => 'DONE',
            'tgl_potong_tf' => date('d/m/Y'),
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
        // Cek pajak: 450450 * 2.5% = 11261.25 => round 11261.25
        $this->assertEquals(11261.25, $submission->cek_pajak_tarif_pph);
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
}

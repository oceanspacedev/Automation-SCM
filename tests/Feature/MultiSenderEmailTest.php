<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Models\Draft;
use App\Models\EmailAccount;
use App\Models\GoogleToken;
use App\Models\Invoice;
use Database\Seeders\EmailAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MultiSenderEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EmailAccountSeeder::class);
    }

    public function test_can_get_email_accounts_list(): void
    {
        $response = $this->getJson('/api/email-accounts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'is_default'],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(5, $data);
        $this->assertEquals('Rebate. MSI', $data[0]['name']);
        $this->assertEquals('ade@mediaselularindonesia.com', $data[0]['email']);
        $this->assertTrue($data[0]['is_default']);
    }

    public function test_rejects_invalid_or_inactive_sender(): void
    {
        $draft = Draft::create([
            'dealer_code' => 'DLR01',
            'dealer_name' => 'Dealer Test',
            'support_amount' => 100000,
            'dpp' => 90000,
            'netpay' => 100000,
            'status' => 'ready',
        ]);

        $invoice = Invoice::create([
            'draft_id' => $draft->id,
            'invoice_number' => 'INV-TEST-001',
            'invoice_type' => 'DSA',
            'dealer_code' => 'DLR01',
            'dealer_name' => 'Dealer Test',
            'netpay' => 100000,
            'email' => 'customer@example.com',
            'status' => 'generated',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/send-email", [
            'sender_id' => 9999, // non-existent
            'email' => 'customer@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Akun pengirim tidak valid atau tidak aktif.',
            ]);
    }

    public function test_sends_email_with_chosen_sender_via_smtp_fallback(): void
    {
        Mail::fake();

        $sender = EmailAccount::where('email', 'admin.scm@completeselular.com')->first();
        $this->assertNotNull($sender);

        $draft = Draft::create([
            'dealer_code' => 'DLR02',
            'dealer_name' => 'Dealer Cabang',
            'support_amount' => 200000,
            'dpp' => 180000,
            'netpay' => 200000,
            'status' => 'ready',
        ]);

        $invoice = Invoice::create([
            'draft_id' => $draft->id,
            'invoice_number' => 'INV-TEST-002',
            'invoice_type' => 'DSA',
            'dealer_code' => 'DLR02',
            'dealer_name' => 'Dealer Cabang',
            'netpay' => 200000,
            'email' => 'toko@example.com',
            'status' => 'generated',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/send-email", [
            'sender_id' => $sender->id,
            'email' => 'toko@example.com',
            'subject' => 'Invoice Program CS September 2026',
            'message' => 'Berikut lampiran invoice program.',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        Mail::assertSent(InvoiceMail::class, function ($mail) use ($sender) {
            return $mail->senderEmail === $sender->email &&
                $mail->senderName === $sender->name &&
                $mail->customSubject === 'Invoice Program CS September 2026';
        });

        $this->assertDatabaseHas('email_logs', [
            'invoice_id' => $invoice->id,
            'sender_email' => 'admin.scm@completeselular.com',
            'sender_name' => 'Program CS',
            'recipient_email' => 'toko@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_sends_email_via_gmail_api_when_google_is_connected(): void
    {
        GoogleToken::create([
            'account_email' => 'ade@mediaselularindonesia.com',
            'access_token' => 'dummy_access_token_123',
            'refresh_token' => 'dummy_refresh_token_123',
            'expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response([
                'id' => 'gmail-msg-abc123xyz',
            ], 200),
        ]);

        $sender = EmailAccount::where('email', 'admin.scm@topselular.com')->first();

        $draft = Draft::create([
            'dealer_code' => 'DLR03',
            'dealer_name' => 'Top Selular Store',
            'support_amount' => 300000,
            'dpp' => 270000,
            'netpay' => 300000,
            'status' => 'ready',
        ]);

        $invoice = Invoice::create([
            'draft_id' => $draft->id,
            'invoice_number' => 'INV-TEST-003',
            'invoice_type' => 'DSA',
            'dealer_code' => 'DLR03',
            'dealer_name' => 'Top Selular Store',
            'netpay' => 300000,
            'email' => 'top@example.com',
            'status' => 'generated',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/send", [
            'sender_id' => $sender->id,
            'email' => 'top@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gmail.googleapis.com') &&
                ! empty($request['raw']);
        });

        $this->assertDatabaseHas('email_logs', [
            'invoice_id' => $invoice->id,
            'sender_email' => 'admin.scm@topselular.com',
            'sender_name' => 'Program Top',
            'recipient_email' => 'top@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_handles_google_send_as_permission_error_gracefully(): void
    {
        GoogleToken::create([
            'account_email' => 'ade@mediaselularindonesia.com',
            'access_token' => 'dummy_access_token_123',
            'refresh_token' => 'dummy_refresh_token_123',
            'expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response([
                'error' => [
                    'message' => 'Invalid From header for user',
                ],
            ], 400),
        ]);

        $sender = EmailAccount::where('email', 'admin.scm@satumediaindonesia.com')->first();

        $draft = Draft::create([
            'dealer_code' => 'DLR04',
            'dealer_name' => 'SMI Partner',
            'support_amount' => 400000,
            'dpp' => 360000,
            'netpay' => 400000,
            'status' => 'ready',
        ]);

        $invoice = Invoice::create([
            'draft_id' => $draft->id,
            'invoice_number' => 'INV-TEST-004',
            'invoice_type' => 'DSA',
            'dealer_code' => 'DLR04',
            'dealer_name' => 'SMI Partner',
            'netpay' => 400000,
            'email' => 'partner@example.com',
            'status' => 'generated',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/send", [
            'sender_id' => $sender->id,
            'email' => 'partner@example.com',
        ]);

        $response->assertStatus(500);
        $this->assertStringContainsString('Send As/delegation', $response->json('message'));

        $this->assertDatabaseHas('email_logs', [
            'invoice_id' => $invoice->id,
            'status' => 'failed',
            'sender_email' => 'admin.scm@satumediaindonesia.com',
        ]);
    }

    public function test_google_status_endpoint(): void
    {
        $response = $this->getJson('/api/google/status');

        $response->assertOk()
            ->assertJsonStructure([
                'is_configured',
                'is_connected',
            ]);
    }
}

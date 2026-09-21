<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\GoogleToken;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class GoogleMailService
{
    /**
     * Get Google OAuth consent screen authorization URL.
     */
    public function getAuthUrl(?string $redirectUri = null): string
    {
        $clientId = (string) config('services.google.client_id');
        $resolvedRedirect = $redirectUri ?: (string) config('services.google.redirect_uri');

        if (empty($clientId)) {
            throw new Exception('GOOGLE_CLIENT_ID belum dikonfigurasi pada .env');
        }

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $resolvedRedirect,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/userinfo.email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params);
    }

    /**
     * Exchange authorization code for access & refresh tokens and persist.
     */
    public function handleCallback(string $code, ?string $redirectUri = null): GoogleToken
    {
        $clientId = (string) config('services.google.client_id');
        $clientSecret = (string) config('services.google.client_secret');
        $resolvedRedirect = $redirectUri ?: (string) config('services.google.redirect_uri');

        $response = Http::withoutVerifying()->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $resolvedRedirect,
        ]);

        if ($response->failed()) {
            $err = $response->json('error_description') ?? $response->body();
            throw new Exception("Gagal menukar kode otorisasi Google: {$err}");
        }

        $data = $response->json();
        $accessToken = (string) ($data['access_token'] ?? '');

        // Fetch account email of user who just authorized
        $accountEmail = null;
        try {
            $userRes = Http::withoutVerifying()->withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo');
            if ($userRes->successful()) {
                $accountEmail = $userRes->json('email');
            }
        } catch (\Throwable) {
            // Ignore if userinfo fetch fails
        }

        $token = GoogleToken::first() ?? new GoogleToken;
        $token->account_email = $accountEmail ?: ($token->account_email ?: 'ade@mediaselularindonesia.com');
        $token->access_token = $accessToken;

        if (! empty($data['refresh_token'])) {
            $token->refresh_token = (string) $data['refresh_token'];
        }

        $token->expires_at = now()->addSeconds((int) ($data['expires_in'] ?? 3600));
        $token->scope = (string) ($data['scope'] ?? '');
        $token->save();

        return $token;
    }

    /**
     * Check if Google account is connected with valid credentials.
     */
    public function isConnected(): bool
    {
        $token = GoogleToken::latest()->first();

        return $token !== null && (! empty($token->access_token) || ! empty($token->refresh_token));
    }

    /**
     * Get connection status details for API/dashboard.
     *
     * @return array{is_configured: bool, is_connected: bool, account_email: ?string, expires_at: ?string}
     */
    public function getStatus(): array
    {
        $token = GoogleToken::latest()->first();
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        return [
            'is_configured' => ! empty($clientId) && ! empty($clientSecret),
            'is_connected' => $this->isConnected(),
            'account_email' => $token?->account_email,
            'expires_at' => $token?->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Disconnect Google account by removing stored tokens.
     */
    public function disconnect(): void
    {
        GoogleToken::truncate();
    }

    /**
     * Get a valid access token, auto-refreshing if expired.
     */
    public function getValidAccessToken(): string
    {
        $token = GoogleToken::latest()->first();

        if (! $token || empty($token->access_token)) {
            throw new Exception('Akun Google belum diotorisasi. Silakan hubungkan akun Google terlebih dahulu.');
        }

        if ($token->isExpired()) {
            return $this->refreshToken($token);
        }

        return (string) $token->access_token;
    }

    /**
     * Refresh access token via Google OAuth token endpoint.
     */
    public function refreshToken(GoogleToken $token): string
    {
        if (empty($token->refresh_token)) {
            throw new Exception('Refresh token tidak tersedia. Silakan hubungkan ulang akun Google.');
        }

        $clientId = (string) config('services.google.client_id');
        $clientSecret = (string) config('services.google.client_secret');

        $response = Http::withoutVerifying()->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $token->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            $err = $response->json('error_description') ?? $response->body();
            throw new Exception("Gagal memperbarui token Google: {$err}");
        }

        $data = $response->json();
        $token->access_token = (string) $data['access_token'];
        $token->expires_at = now()->addSeconds((int) ($data['expires_in'] ?? 3600));
        $token->save();

        return (string) $token->access_token;
    }

    /**
     * Send invoice email via Gmail API with selected sender alias (Send As).
     *
     * @param  array<int, string>  $cc
     * @return array{success: bool, message_id: string}
     */
    public function sendInvoiceMail(
        Invoice $invoice,
        EmailAccount $sender,
        string $to,
        array $cc = [],
        ?string $subject = null,
        ?string $customMessage = null
    ): array {
        $accessToken = $this->getValidAccessToken();

        // 1. Generate PDF attachment in memory
        $viewName = match ($invoice->invoice_type) {
            'DSA' => 'invoices.dsa',
            'REGULAR', 'REGULER' => 'invoices.regular',
            default => 'invoices.nps-fl',
        };

        $pdfOutput = Pdf::loadView($viewName, ['invoice' => $invoice])
            ->setPaper('a4', 'portrait')
            ->output();

        // 2. Build MIME message RFC 2822 using Symfony Email
        $email = (new Email)
            ->from(new Address($sender->email, $sender->name))
            ->to($to)
            ->subject($subject ?: "Invoice {$invoice->invoice_number} - {$invoice->dealer_name}")
            ->html(view('emails.invoice', [
                'invoice' => $invoice,
                'customMessage' => $customMessage,
                'senderName' => $sender->name,
                'senderEmail' => $sender->email,
            ])->render())
            ->attach($pdfOutput, "{$invoice->invoice_number}.pdf", 'application/pdf');

        foreach ($cc as $ccEmail) {
            $trimmed = trim((string) $ccEmail);
            if (! empty($trimmed) && filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                $email->addCc($trimmed);
            }
        }

        // 3. Base64url encode MIME string for Gmail API
        $rawMime = $email->toString();
        $base64Url = rtrim(strtr(base64_encode($rawMime), '+/', '-_'), '=');

        // 4. Send via Gmail API
        $response = Http::withoutVerifying()
            ->withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $base64Url,
            ]);

        if ($response->failed()) {
            $status = $response->status();
            $errData = $response->json();
            $rawMsg = (string) ($errData['error']['message'] ?? $response->body());

            // Handle Google Workspace Send As / delegation permission failure
            if ($status === 400 || $status === 403) {
                $lower = strtolower($rawMsg);
                if (str_contains($lower, 'from') ||
                    str_contains($lower, 'sender') ||
                    str_contains($lower, 'delegate') ||
                    str_contains($lower, 'access denied') ||
                    str_contains($lower, 'invalid argument')
                ) {
                    throw new Exception("Email pengirim ({$sender->email}) belum memiliki izin untuk mengirim menggunakan alamat ini. Periksa konfigurasi Google Workspace Send As/delegation.");
                }
            }

            throw new Exception("Gagal mengirim email via Gmail API (HTTP {$status}): {$rawMsg}");
        }

        return [
            'success' => true,
            'message_id' => (string) $response->json('id'),
        ];
    }
}

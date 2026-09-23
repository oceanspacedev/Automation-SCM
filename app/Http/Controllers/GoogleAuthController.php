<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Services\GoogleMailService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected GoogleMailService $googleMailService
    ) {}

    /**
     * Redirect user to Google OAuth consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        try {
            $url = $this->googleMailService->getAuthUrl();

            return redirect()->away($url);
        } catch (Exception $e) {
            return redirect('/invoices')->with('error', $e->getMessage());
        }
    }

    /**
     * Handle incoming OAuth callback from Google.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $error = $request->input('error_description', $request->input('error'));

            return redirect('/invoices?google_error='.urlencode((string) $error));
        }

        $code = (string) $request->input('code');
        if (empty($code)) {
            return redirect('/invoices?google_error='.urlencode('Kode otorisasi tidak ditemukan dari Google.'));
        }

        try {
            $token = $this->googleMailService->handleCallback($code);

            if (! empty($token->account_email) && Schema::hasTable('email_accounts')) {
                EmailAccount::firstOrCreate(
                    ['email' => $token->account_email],
                    [
                        'name' => 'Google Workspace',
                        'is_default' => false,
                        'is_active' => true,
                    ]
                );
            }

            return redirect('/invoices?google_connected=1&account='.urlencode((string) $token->account_email));
        } catch (Exception $e) {
            return redirect('/invoices?google_error='.urlencode($e->getMessage()));
        }
    }

    /**
     * Return Google connection status.
     */
    public function status(): JsonResponse
    {
        return response()->json($this->googleMailService->getStatus());
    }

    /**
     * Disconnect Google account.
     */
    public function disconnect(): JsonResponse
    {
        $this->googleMailService->disconnect();

        return response()->json([
            'success' => true,
            'message' => 'Akun Google berhasil diputuskan.',
        ]);
    }
}

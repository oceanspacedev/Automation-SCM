<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WaGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember', false);

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'user' => Auth::user(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Email atau password salah.',
        ], 422);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Kirim OTP ke nomor WhatsApp yang terdaftar di akun.
     * POST /api/auth/wa-otp/send
     * Body: { "whatsapp": "0812xxxx" }
     */
    public function sendWaOtp(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp' => ['required', 'string', 'min:9', 'max:20'],
        ]);

        $normalized = $this->normalizePhone($request->input('whatsapp'));

        // Cari user berdasarkan nomor WA
        $user = User::where('whatsapp', $normalized)
            ->orWhere('whatsapp', $request->input('whatsapp'))
            ->first();

        if (! $user) {
            // Respons ambigu agar tidak bocorkan info nomor terdaftar atau tidak
            return response()->json([
                'success' => true,
                'message' => 'Jika nomor terdaftar, kode OTP akan segera dikirim via WhatsApp.',
            ]);
        }

        // Rate limit: max 3 kali kirim per 10 menit per nomor
        $rateLimitKey = "wa_otp_rate:{$normalized}";
        if (Cache::get($rateLimitKey, 0) >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak permintaan OTP. Coba lagi dalam beberapa menit.',
            ], 429);
        }

        // Generate OTP 6 digit
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "wa_otp:{$normalized}";

        // Simpan OTP di cache selama 5 menit
        Cache::put($cacheKey, [
            'otp' => $otp,
            'user_id' => $user->id,
            'attempts' => 0,
        ], now()->addMinutes(5));

        // Increment rate limit counter
        Cache::put($rateLimitKey, Cache::get($rateLimitKey, 0) + 1, now()->addMinutes(10));

        // Kirim OTP via WAGhub
        $waService = new WaGatewayService;
        $sent = $waService->sendText(
            $normalized,
            "🔐 *Kode OTP Login SCM*\n\nKode Anda: *{$otp}*\n\nBerlaku 5 menit. Jangan bagikan kode ini kepada siapa pun."
        );

        if (! $sent) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP via WhatsApp. Silakan coba lagi.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP berhasil dikirim ke WhatsApp Anda.',
        ]);
    }

    /**
     * Verifikasi OTP dan login.
     * POST /api/auth/wa-otp/verify
     * Body: { "whatsapp": "0812xxxx", "otp": "123456" }
     */
    public function verifyWaOtp(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $normalized = $this->normalizePhone($request->input('whatsapp'));
        $cacheKey = "wa_otp:{$normalized}";
        $stored = Cache::get($cacheKey);

        if (! $stored) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak ditemukan atau sudah kedaluwarsa. Silakan minta kode baru.',
            ], 422);
        }

        // Max 5 percobaan
        if ($stored['attempts'] >= 5) {
            Cache::forget($cacheKey);

            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan. Silakan minta kode OTP baru.',
            ], 422);
        }

        if ($stored['otp'] !== $request->input('otp')) {
            // Increment attempts
            $stored['attempts']++;
            Cache::put($cacheKey, $stored, now()->addMinutes(5));

            $remaining = 5 - $stored['attempts'];

            return response()->json([
                'success' => false,
                'message' => "Kode OTP salah. Sisa percobaan: {$remaining}.",
            ], 422);
        }

        // OTP valid – login user
        $user = User::find($stored['user_id']);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak ditemukan.',
            ], 422);
        }

        Cache::forget($cacheKey);

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'user' => $user,
        ]);
    }

    /**
     * Normalisasi nomor telepon ke format 628xxx.
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '62')) {
            return '62'.$phone;
        }

        return $phone;
    }
}

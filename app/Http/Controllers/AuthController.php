<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WaGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Registrasi pengguna baru mandiri (status default: nonaktif / menunggu ACC admin).
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'whatsapp' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in(['scm', 'ar', 'telemarketing'])],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'email.unique' => 'Email ini sudah terdaftar di sistem.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 6 karakter.',
        ]);

        $normalizedWa = ! empty($validated['whatsapp']) ? $this->normalizePhone($validated['whatsapp']) : null;
        $role = $validated['role'];
        $defaultPermissions = User::getDefaultPermissionsForRole($role);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'whatsapp' => $normalizedWa,
            'role' => $role,
            'permissions' => $defaultPermissions,
            'password' => Hash::make($validated['password']),
            'is_active' => false, // Menunggu persetujuan (ACC) Admin
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Akun Anda sedang menunggu persetujuan (ACC) dari Administrator sebelum dapat digunakan untuk login.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['nullable', 'string'],
            'login' => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = trim((string) ($request->input('login') ?: $request->input('email')));

        if (empty($loginInput)) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau email wajib diisi.',
            ], 422);
        }

        $remember = $request->boolean('remember', false);
        $password = (string) $request->input('password');

        // Coba login dengan email terlebih dahulu jika format email, atau username (name)
        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $attempted = Auth::attempt([$field => $loginInput, 'password' => $password], $remember);

        // Jika gagal dan dicoba nama, coba juga sebagai email (atau sebaliknya)
        if (! $attempted) {
            $altField = ($field === 'email') ? 'name' : 'email';
            $attempted = Auth::attempt([$altField => $loginInput, 'password' => $password], $remember);
        }

        if ($attempted) {
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda belum aktif atau sedang menunggu persetujuan (ACC) dari Admin.',
                ], 403);
            }

            $request->session()->regenerate();

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'user' => $user,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Username/email atau password salah.',
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
        $raw = preg_replace('/\D/', '', $request->input('whatsapp'));
        $variants = array_filter(array_unique([
            $normalized,
            $raw,
            $request->input('whatsapp'),
            '0'.substr($normalized, 2),
            substr($normalized, 2),
            '+'.$normalized,
        ]));

        // Cari user berdasarkan nomor WA (mendukung format 08xxx, 628xxx, 8xxx)
        $user = User::whereIn('whatsapp', $variants)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor WhatsApp belum terdaftar di sistem SCM.',
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda belum aktif atau sedang menunggu persetujuan (ACC) dari Admin.',
            ], 403);
        }

        // Rate limit: max 10 kali kirim per 15 menit per nomor
        $rateLimitKey = "wa_otp_rate:{$normalized}";
        if (Cache::get($rateLimitKey, 0) >= 10) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak permintaan OTP. Coba lagi dalam beberapa menit.',
            ], 429);
        }

        // Generate OTP 6 digit
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "wa_otp:{$normalized}";

        // Kirim OTP via WAGhub
        $waService = new WaGatewayService;
        $sent = $waService->sendText(
            $normalized,
            "🔐 *Kode OTP Login SCM*\n\nKode Anda: *{$otp}*\n\nBerlaku 15 menit. Jangan bagikan kode ini kepada siapa pun."
        );

        if (! $sent) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP via WhatsApp gateway. Pastikan nomor aktif atau coba lagi.',
            ], 503);
        }

        $otpData = [
            'otp' => $otp,
            'user_id' => $user->id,
            'whatsapp' => $normalized,
            'attempts' => 0,
        ];

        // Simpan OTP di cache selama 15 menit
        Cache::put($cacheKey, $otpData, now()->addMinutes(15));
        Cache::put("wa_otp_user:{$user->id}", $otpData, now()->addMinutes(15));
        if (! empty($user->whatsapp) && $user->whatsapp !== $normalized) {
            $userNorm = $this->normalizePhone($user->whatsapp);
            Cache::put("wa_otp:{$userNorm}", $otpData, now()->addMinutes(15));
        }

        // Increment rate limit counter
        Cache::put($rateLimitKey, Cache::get($rateLimitKey, 0) + 1, now()->addMinutes(15));

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
            'otp' => ['required', 'string'],
        ]);

        $rawPhone = (string) $request->input('whatsapp');
        $normalized = $this->normalizePhone($rawPhone);
        $inputOtp = preg_replace('/\D/', '', (string) $request->input('otp'));

        if (strlen($inputOtp) !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP harus 6 digit angka.',
            ], 422);
        }

        $cacheKey = "wa_otp:{$normalized}";
        $rateLimitKey = "wa_otp_rate:{$normalized}";
        $stored = Cache::get($cacheKey);

        // Fallback jika format nomor telepon saat request verify berbeda dengan saat send
        if (! $stored) {
            $rawDigits = preg_replace('/\D/', '', $rawPhone);
            $variants = array_filter(array_unique([
                $normalized,
                $rawDigits,
                $rawPhone,
                '0'.substr($normalized, 2),
                substr($normalized, 2),
                '+'.$normalized,
            ]));
            $userFound = User::whereIn('whatsapp', $variants)->first();
            if ($userFound) {
                $stored = Cache::get("wa_otp_user:{$userFound->id}")
                    ?? Cache::get('wa_otp:'.$this->normalizePhone((string) $userFound->whatsapp));
            }
        }

        if (! $stored) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak ditemukan atau sudah kedaluwarsa. Silakan minta kode baru.',
            ], 422);
        }

        // Max 5 percobaan
        if ($stored['attempts'] >= 5) {
            Cache::forget($cacheKey);
            if (isset($stored['user_id'])) {
                Cache::forget("wa_otp_user:{$stored['user_id']}");
            }

            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan. Silakan minta kode OTP baru.',
            ], 422);
        }

        if ((string) $stored['otp'] !== $inputOtp) {
            // Increment attempts
            $stored['attempts']++;
            Cache::put($cacheKey, $stored, now()->addMinutes(15));
            if (isset($stored['user_id'])) {
                Cache::put("wa_otp_user:{$stored['user_id']}", $stored, now()->addMinutes(15));
            }

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

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda belum aktif atau sedang menunggu persetujuan (ACC) dari Admin.',
            ], 403);
        }

        Cache::forget($cacheKey);
        Cache::forget($rateLimitKey);
        if (isset($stored['user_id'])) {
            Cache::forget("wa_otp_user:{$stored['user_id']}");
        }
        if (! empty($user->whatsapp)) {
            Cache::forget('wa_otp:'.$this->normalizePhone((string) $user->whatsapp));
        }

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

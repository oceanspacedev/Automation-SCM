<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Tampilkan daftar master user beserta statistik peran.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Filter pencarian nama / email / nomor WA
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        // Filter role
        if ($role = $request->input('role')) {
            if ($role !== 'all') {
                $query->where('role', $role);
            }
        }

        // Filter status aktif/nonaktif
        if ($request->has('is_active') && $request->input('is_active') !== 'all' && $request->input('is_active') !== null) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        // Hitung statistik untuk summary card
        $stats = [
            'total' => User::count(),
            'admin' => User::where('role', 'admin')->count(),
            'scm' => User::where('role', 'scm')->count(),
            'ar' => User::where('role', 'ar')->count(),
            'telemarketing' => User::where('role', 'telemarketing')->count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'pending' => User::where('is_active', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $users,
            'stats' => $stats,
            'available_permissions' => User::AVAILABLE_PERMISSIONS,
            'default_permissions' => [
                'admin' => User::getDefaultPermissionsForRole('admin'),
                'scm' => User::getDefaultPermissionsForRole('scm'),
                'ar' => User::getDefaultPermissionsForRole('ar'),
                'telemarketing' => User::getDefaultPermissionsForRole('telemarketing'),
            ],
        ]);
    }

    /**
     * Tambah user baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'whatsapp' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in(['admin', 'scm', 'ar', 'telemarketing'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(User::AVAILABLE_PERMISSIONS))],
            'password' => ['required', 'string', 'min:6'],
            'is_active' => ['boolean'],
        ]);

        $cleanWa = $this->cleanWhatsappNumber($validated['whatsapp'] ?? null);

        // Jika permissions kosong / tidak diisi, gunakan default permissions berdasarkan role
        $permissions = $validated['permissions'] ?? User::getDefaultPermissionsForRole($validated['role']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'whatsapp' => $cleanWa,
            'role' => $validated['role'],
            'permissions' => $permissions,
            'password' => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan.',
            'data' => $user,
        ], 201);
    }

    /**
     * Update data user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'whatsapp' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in(['admin', 'scm', 'ar', 'telemarketing'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(User::AVAILABLE_PERMISSIONS))],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['boolean'],
        ]);

        // Proteksi: jangan izinkan admin terakhir mengubah role-nya sendiri atau dinonaktifkan
        $targetActive = $validated['is_active'] ?? $user->is_active;
        $targetRole = $validated['role'];

        if ($user->role === 'admin' && ($targetRole !== 'admin' || ! $targetActive)) {
            $otherAdminCount = User::where('role', 'admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherAdminCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat mengubah role atau menonaktifkan satu-satunya akun Admin aktif di sistem.',
                ], 422);
            }
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->whatsapp = $this->cleanWhatsappNumber($validated['whatsapp'] ?? null);
        $user->role = $validated['role'];
        $user->is_active = $targetActive;

        if (array_key_exists('permissions', $validated)) {
            $user->permissions = $validated['permissions'];
        }

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Data user berhasil diperbarui.',
            'data' => $user,
        ]);
    }

    /**
     * Hapus akun user.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Jangan izinkan hapus diri sendiri
        if (Auth::id() === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.',
            ], 422);
        }

        // Jangan izinkan hapus jika admin terakhir
        if ($user->role === 'admin') {
            $otherAdminCount = User::where('role', 'admin')
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherAdminCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus satu-satunya akun Admin yang tersisa.',
                ], 422);
            }
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus.',
        ]);
    }

    /**
     * Toggle status aktif / nonaktif cepat.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (Auth::id() === $user->id && $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        if ($user->role === 'admin' && $user->is_active) {
            $otherAdminCount = User::where('role', 'admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherAdminCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menonaktifkan satu-satunya Admin aktif yang tersisa.',
                ], 422);
            }
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Status user berhasil diubah menjadi '.($user->is_active ? 'Aktif' : 'Nonaktif').'.',
            'data' => $user,
        ]);
    }

    /**
     * Setujui (ACC) akun user yang mendaftar.
     */
    public function approve(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->is_active = true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil disetujui (ACC) dan sekarang sudah aktif.",
            'data' => $user,
        ]);
    }

    /**
     * Standardisasi nomor WhatsApp ke format 628xxx
     */
    private function cleanWhatsappNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return null;
        }

        if (str_starts_with($clean, '08')) {
            return '628'.substr($clean, 2);
        }

        if (str_starts_with($clean, '8')) {
            return '62'.$clean;
        }

        return $clean;
    }
}

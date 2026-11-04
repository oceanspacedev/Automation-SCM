<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use RuntimeException;

class UserController extends Controller
{
    /**
     * Tampilkan daftar master user beserta statistik peran.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeUserManagement($request);

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
        $this->authorizeUserManagement($request, newRole: (string) $request->input('role'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'whatsapp' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in(['admin', 'scm', 'ar', 'telemarketing'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(User::AVAILABLE_PERMISSIONS))],
            'password' => ['required', 'string', 'min:6'],
            'is_active' => ['boolean'],
        ]);

        $cleanWa = User::normalizeWhatsapp($validated['whatsapp'] ?? null);

        // Jika permissions kosong / tidak diisi, gunakan default permissions berdasarkan role
        $permissions = $validated['permissions'] ?? User::getDefaultPermissionsForRole($validated['role']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
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
     * Buat akun dari file Excel berisi nama, nomor WhatsApp, dan (opsional) email & peran, mis. daftar pengguna atau
     * file Master Customer CV TOP (telemarketing), satu akun per nomor. Baris tanpa peran memakai peran yang dipilih.
     */
    public function import(Request $request, UserImportService $importService): JsonResponse
    {
        $role = (string) $request->input('role', 'telemarketing');
        $this->authorizeUserManagement($request, newRole: $role);

        $validated = $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:51200'],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['nullable', Rule::in(['admin', 'scm', 'ar', 'telemarketing'])],
        ], [
            'file.required' => 'Pilih file Excel terlebih dahulu.',
            'file.extensions' => 'File harus berformat .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 50 MB.',
            'password.min' => 'Password awal minimal 6 karakter.',
        ]);

        try {
            $result = $importService->import(
                $request->file('file')->getRealPath(),
                $validated['password'] ?? null,
                $validated['role'] ?? 'telemarketing',
                canCreateAdmin: (bool) $request->user()?->isAdmin(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (SpreadsheetException) {
            return response()->json(['success' => false, 'message' => 'File Excel tidak bisa dibaca.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => count($result['created']).' akun dibuat.',
            'data' => $result,
        ]);
    }

    /**
     * Update data user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->authorizeUserManagement($request, $user, (string) $request->input('role'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
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
        $user->email = $validated['email'] ?? null;
        $user->whatsapp = User::normalizeWhatsapp($validated['whatsapp'] ?? null);
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
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->authorizeUserManagement($request, $user);

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
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->authorizeUserManagement($request, $user);

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
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->authorizeUserManagement($request, $user);
        $user->is_active = true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil disetujui (ACC) dan sekarang sudah aktif.",
            'data' => $user,
        ]);
    }

    /**
     * Managing accounts needs the Master User access, and only an Admin may create, change or remove an Admin account;
     * otherwise anyone with Master User access could make themselves Admin.
     */
    private function authorizeUserManagement(Request $request, ?User $target = null, ?string $newRole = null): void
    {
        $actor = $request->user();

        abort_unless($actor?->isAdmin() || $actor?->hasPermission('master_user'), 403, 'Anda tidak punya akses ke Master User.');
        abort_if(($target?->role === 'admin' || $newRole === 'admin') && ! $actor->isAdmin(), 403, 'Hanya Admin yang bisa membuat atau mengubah akun Admin.');
    }

    /**
     * Ambil daftar kontak tim telemarketing aktif untuk dropdown kirim WA.
     */
    public function telemarketingContacts(): JsonResponse
    {
        $contacts = User::where('role', 'telemarketing')
            ->where('is_active', true)
            ->whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email', 'whatsapp']);

        $defaultPhone = config('services.wag.telemarketing_phone') ?: config('services.wag.ar_phone', '081224290502');

        return response()->json([
            'success' => true,
            'default_phone' => $defaultPhone,
            'contacts' => $contacts,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
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

        /** @var User $actor */
        $actor = $request->user();
        $actorCompanies = $actor->companyAccessSlugs();

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
        if (! $actor->isAdmin()) {
            $users = $users
                ->filter(fn (User $u): bool => array_intersect($u->companyAccessSlugs(), $actorCompanies) !== [])
                ->values();
        }

        // Hitung statistik untuk summary card
        $stats = [
            'total' => User::count(),
            'admin' => User::where('role', 'admin')->count(),
            'scm' => User::where('role', 'scm')->count(),
            'ar' => User::where('role', 'ar')->count(),
            'telemarketing' => User::where('role', 'telemarketing')->count(),
            'pajak' => User::where('role', 'pajak')->count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'pending' => User::where('is_active', false)->count(),
        ];

        $companies = Company::query()
            ->orderBy('id')
            ->get(['id', 'slug', 'name'])
            ->filter(fn (Company $company): bool => $company->canBeAccessedBy($actor))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $users,
            'stats' => $stats,
            'companies' => $companies,
            'available_permissions' => User::AVAILABLE_PERMISSIONS,
            'default_permissions' => [
                'admin' => User::getDefaultPermissionsForRole('admin'),
                'scm' => User::getDefaultPermissionsForRole('scm'),
                'ar' => User::getDefaultPermissionsForRole('ar'),
                'telemarketing' => User::getDefaultPermissionsForRole('telemarketing'),
                'pajak' => User::getDefaultPermissionsForRole('pajak'),
            ],
        ]);
    }

    /**
     * Tambah user baru.
     */
    public function updateRolePermissions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya Admin yang dapat mengatur hak akses role.');
        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'scm', 'ar', 'telemarketing', 'pajak'])],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(array_keys(User::AVAILABLE_PERMISSIONS))],
        ]);
        if ($validated['role'] === 'admin' && ! in_array('master_user', $validated['permissions'], true)) {
            return response()->json(['message' => 'Role Admin harus tetap memiliki akses Master User.'], 422);
        }
        foreach (['drafts', 'invoices', 'data_program', 'form_program', 'master_program', 'master_dealer', 'master_customer', 'master_user'] as $module) {
            if (array_intersect([$module.'_create', $module.'_edit', $module.'_delete'], $validated['permissions']) && ! in_array($module, $validated['permissions'], true)) {
                return response()->json(['message' => 'Aktifkan izin lihat modul sebelum mengaktifkan tambah, edit atau hapus.'], 422);
            }
        }
        AppSetting::write('role_permissions_'.$validated['role'], json_encode($validated['permissions'], JSON_THROW_ON_ERROR));

        return response()->json(['success' => true, 'message' => 'Hak akses role berhasil disimpan dan berlaku untuk seluruh pengguna dengan role ini.']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'whatsapp' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in(['admin', 'scm', 'ar', 'telemarketing', 'pajak'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(User::AVAILABLE_PERMISSIONS))],
            'company_slugs' => ['sometimes', 'array', 'min:1'],
            'company_slugs.*' => ['string', 'distinct', Rule::exists('companies', 'slug')],
            'password' => ['required', ...User::passwordRules()],
            'is_active' => ['boolean'],
        ], [
            ...User::passwordMessages(),
            'company_slugs.min' => 'Pilih minimal satu perusahaan.',
        ]);

        $companySlugs = $this->resolveCompanySlugs($validated['company_slugs'] ?? null, $validated['role']);
        $this->authorizeUserManagement($request, newRole: $validated['role'], newCompanySlugs: $companySlugs);

        $cleanWa = User::normalizeWhatsapp($validated['whatsapp'] ?? null);

        // Jika permissions kosong / tidak diisi, gunakan default permissions berdasarkan role
        $permissions = $validated['permissions'] ?? User::getDefaultPermissionsForRole($validated['role']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'whatsapp' => $cleanWa,
            'role' => $validated['role'],
            'permissions' => $permissions,
            'company_slugs' => $companySlugs,
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

        $validated = $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:51200'],
            'password' => ['nullable', ...User::passwordRules()],
            'role' => ['nullable', Rule::in(['admin', 'scm', 'ar', 'telemarketing', 'pajak'])],
            'company_slugs' => ['nullable', 'array', 'min:1'],
            'company_slugs.*' => ['string', 'distinct', Rule::exists('companies', 'slug')],
        ], [
            'file.required' => 'Pilih file Excel terlebih dahulu.',
            'file.extensions' => 'File harus berformat .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 50 MB.',
            'company_slugs.min' => 'Pilih minimal satu perusahaan.',
            ...User::passwordMessages(label: 'Password awal'),
        ]);

        $defaultCompanies = $this->resolveCompanySlugs($validated['company_slugs'] ?? null, $role);
        $this->authorizeUserManagement($request, newRole: $role, newCompanySlugs: $defaultCompanies);

        /** @var User $actor */
        $actor = $request->user();
        $allowedCompanies = $actor->isAdmin() ? null : $actor->companyAccessSlugs();

        try {
            $result = $importService->import(
                $request->file('file')->getRealPath(),
                $validated['password'] ?? null,
                $validated['role'] ?? 'telemarketing',
                canCreateAdmin: $actor->isAdmin(),
                defaultCompanySlugs: $defaultCompanies,
                allowedCompanySlugs: $allowedCompanies,
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'whatsapp' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in(['admin', 'scm', 'ar', 'telemarketing', 'pajak'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(User::AVAILABLE_PERMISSIONS))],
            'company_slugs' => ['sometimes', 'array', 'min:1'],
            'company_slugs.*' => ['string', 'distinct', Rule::exists('companies', 'slug')],
            'password' => ['nullable', ...User::passwordRules()],
            'is_active' => ['boolean'],
        ], [
            ...User::passwordMessages(),
            'company_slugs.min' => 'Pilih minimal satu perusahaan.',
        ]);

        $targetCompanySlugs = array_key_exists('company_slugs', $validated)
            ? $this->resolveCompanySlugs($validated['company_slugs'], $validated['role'])
            : $user->companyAccessSlugs();

        $this->authorizeUserManagement($request, $user, (string) $validated['role'], $targetCompanySlugs);

        // Proteksi: jangan izinkan admin terakhir mengubah role-nya sendiri, dinonaktifkan, atau kehilangan akses perusahaan
        $targetActive = $validated['is_active'] ?? $user->is_active;
        $targetRole = $validated['role'];

        if ($message = $this->leavesCompanyWithoutActiveAdmin($user, $targetRole === 'admin' && $targetActive ? $targetCompanySlugs : [])) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'] ?? null;
        $user->whatsapp = User::normalizeWhatsapp($validated['whatsapp'] ?? null);
        if ($user->role !== $validated['role'] || $validated['role'] === 'admin') {
            $user->permissions = null;
        }
        $user->role = $validated['role'];
        $user->company_slugs = $targetCompanySlugs;
        $user->is_active = $targetActive;

        if (array_key_exists('permissions', $validated) && $validated['role'] !== 'admin') {
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

        if ($message = $this->leavesCompanyWithoutActiveAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
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

        if ($message = $this->leavesCompanyWithoutActiveAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
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
     * Check whether removing active Admin access from $user (or limiting it to $nextSlugs) would leave any company
     * without an active Admin account.
     *
     * @param  list<string>  $nextSlugs
     */
    private function leavesCompanyWithoutActiveAdmin(User $user, array $nextSlugs = []): ?string
    {
        if ($user->role !== 'admin' || ! $user->is_active) {
            return null;
        }

        $otherActiveAdmins = User::where('role', 'admin')
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->get();

        if ($otherActiveAdmins->isEmpty() && $nextSlugs === []) {
            return 'Satu-satunya akun Admin aktif di sistem tidak dapat dinonaktifkan, diubah perannya, atau dihapus.';
        }

        $covered = array_unique([
            ...$otherActiveAdmins->flatMap(fn (User $admin): array => $admin->companyAccessSlugs())->all(),
            ...$nextSlugs,
        ]);

        if (array_diff($user->companyAccessSlugs(), $covered) !== []) {
            return 'Harus ada minimal satu akun Admin aktif yang dapat mengakses setiap perusahaan.';
        }

        return null;
    }

    /**
     * Managing accounts needs the Master User access, and only an Admin may create, change or remove an Admin account;
     * non-admins can also only manage accounts and assign access within their own allowed companies.
     *
     * @param  list<string>|null  $newCompanySlugs
     */
    private function authorizeUserManagement(
        Request $request,
        ?User $target = null,
        ?string $newRole = null,
        ?array $newCompanySlugs = null,
    ): void {
        $actor = $request->user();

        abort_unless($actor?->isAdmin() || $actor?->hasPermission('master_user'), 403, 'Anda tidak punya akses ke Master User.');
        abort_if(($target?->role === 'admin' || $newRole === 'admin') && ! $actor->isAdmin(), 403, 'Hanya Admin yang bisa membuat atau mengubah akun Admin.');

        if (! $actor->isAdmin()) {
            $actorCompanies = $actor->companyAccessSlugs();
            if ($target !== null && array_diff($target->companyAccessSlugs(), $actorCompanies) !== []) {
                abort(403, 'Anda tidak dapat mengelola akun di luar perusahaan Anda.');
            }
            if ($newCompanySlugs !== null && array_diff($newCompanySlugs, $actorCompanies) !== []) {
                abort(403, 'Anda hanya dapat memberikan akses ke perusahaan yang Anda akses.');
            }
        }
    }

    /**
     * Normalize requested company slugs in company table order, defaulting to both companies for Admin and the active
     * company otherwise.
     *
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    private function resolveCompanySlugs(?array $requested, string $role): array
    {
        $available = Company::query()->orderBy('id')->pluck('slug')->all();

        if ($requested !== null) {
            $ordered = array_values(array_intersect($available, $requested));
            if ($ordered !== []) {
                return $ordered;
            }
        }

        if ($role === 'admin' && $available !== []) {
            return $available;
        }

        return [Company::findOrFail(app(CompanyContext::class)->id())->slug];
    }

    /**
     * Ambil daftar kontak tim telemarketing aktif pada perusahaan aktif untuk dropdown kirim WA.
     */
    public function telemarketingContacts(): JsonResponse
    {
        $companySlug = Company::findOrFail(app(CompanyContext::class)->id())->slug;
        $contacts = User::where('role', 'telemarketing')
            ->where('is_active', true)
            ->whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email', 'whatsapp', 'role', 'company_slugs', 'is_active'])
            ->filter(fn (User $user): bool => $user->canAccessCompanySlug($companySlug))
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'whatsapp' => $user->whatsapp,
            ])
            ->values();

        $defaultPhone = config('services.wag.telemarketing_phone') ?: config('services.wag.ar_phone', '081224290502');

        return response()->json([
            'success' => true,
            'default_phone' => $defaultPhone,
            'contacts' => $contacts,
        ]);
    }
}

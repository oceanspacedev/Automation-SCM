<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'whatsapp', 'avatar_path', 'role', 'permissions', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token', 'avatar_path'])]
#[Appends(['avatar_url', 'effective_permissions'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Daftar seluruh permission yang tersedia di aplikasi (Dashboard terbuka untuk semua user, jadi tidak termasuk)
     */
    public const AVAILABLE_PERMISSIONS = [
        'drafts_create' => 'Draft - Tambah',
        'invoices_create' => 'Invoice - Tambah',
        'data_program_create' => 'Data Program - Tambah',
        'form_program_create' => 'Form Program - Tambah',
        'master_program_create' => 'Master Program - Tambah',
        'master_program_edit' => 'Master Program - Edit',
        'master_program_delete' => 'Master Program - Hapus',
        'master_dealer_create' => 'Master Dealer - Tambah',
        'master_dealer_edit' => 'Master Dealer - Edit',
        'master_dealer_delete' => 'Master Dealer - Hapus',
        'master_customer_create' => 'Master Badan Usaha - Tambah',
        'master_customer_edit' => 'Master Badan Usaha - Edit',
        'master_customer_delete' => 'Master Badan Usaha - Hapus',
        'master_user_create' => 'Master User - Tambah',
        'master_user_edit' => 'Master User - Edit',
        'master_user_delete' => 'Master User - Hapus',
        'drafts' => 'Draft',
        'invoices' => 'Invoice',
        'form_program' => 'Form Program',
        'data_program' => 'Data Program',
        'drafts_edit' => 'Draft - Edit',
        'drafts_delete' => 'Draft - Hapus',
        'invoices_edit' => 'Invoice - Edit',
        'invoices_delete' => 'Invoice - Hapus',
        'data_program_edit' => 'Data Program - Edit',
        'data_program_delete' => 'Data Program - Hapus',
        'form_program_edit' => 'Form Program - Edit',
        'form_program_delete' => 'Form Program - Hapus',
        'riwayat_program' => 'Riwayat Program & Approval',
        'email_logs' => 'Riwayat Email',
        'master_program' => 'Master Program',
        'master_dealer' => 'Master Dealer',
        'master_customer' => 'Master Badan Usaha',
        'master_user' => 'Master User',
    ];

    /**
     * Rules for a new account password (daftar, ganti password, Master User, import): at least 8 characters with
     * both letters and numbers. Signing in doesn't check them, so older passwords keep working.
     *
     * @return list<string>
     */
    public static function passwordRules(): array
    {
        return ['string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'];
    }

    /**
     * Messages for passwordRules().
     *
     * @return array<string, string>
     */
    public static function passwordMessages(string $field = 'password', string $label = 'Password'): array
    {
        return [
            "{$field}.min" => "{$label} minimal 8 karakter.",
            "{$field}.regex" => "{$label} harus berisi huruf dan angka.",
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Public URL of the profile photo, or null when the user has none.
     *
     * @return Attribute<?string, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null);
    }

    /**
     * Store a WhatsApp number as 628xxx ("0812…" and "812…" become "62812…"); null when empty.
     */
    public static function normalizeWhatsapp(?string $phone): ?string
    {
        $clean = preg_replace('/[^0-9]/', '', (string) $phone);

        if ($clean === '') {
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

    /**
     * Default permission bawaan per role
     */
    public static function getDefaultPermissionsForRole(string $role): array
    {
        $configured = AppSetting::read('role_permissions_'.$role);
        if ($configured !== null) {
            return json_decode($configured, true, flags: JSON_THROW_ON_ERROR);
        }

        return match ($role) {
            'admin', 'scm' => [
                'drafts_create',
                'invoices_create',
                'data_program_create',
                'form_program_create',
                'master_program_create',
                'master_program_edit',
                'master_program_delete',
                'master_dealer_create',
                'master_dealer_edit',
                'master_dealer_delete',
                'master_customer_create',
                'master_customer_edit',
                'master_customer_delete',
                'master_user_create',
                'master_user_edit',
                'master_user_delete',
                'drafts_edit',
                'drafts_delete',
                'invoices_edit',
                'invoices_delete',
                'data_program_edit',
                'data_program_delete',
                'form_program_edit',
                'form_program_delete',
                'drafts',
                'invoices',
                'form_program',
                'data_program',
                'riwayat_program',
                'email_logs',
                'master_program',
                'master_dealer',
                'master_customer',
                'master_user',
            ],
            'ar' => [
                'drafts',
                'invoices',
                'riwayat_program',
            ],
            'telemarketing' => [
                'form_program',
                'riwayat_program',
            ],
            'pajak' => [
                'data_program',
                'data_program_edit',
                'invoices',
                'riwayat_program',
            ],
            default => [
                'form_program',
            ],
        };
    }

    /**
     * Cek apakah user memiliki hak akses tertentu
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        $role = $this->role ?? 'scm';
        $rolePermissions = self::getDefaultPermissionsForRole($role);

        // Role Admin selalu mengikuti hak akses Admin (bawaan penuh atau konfigurasi Role Permission)
        if ($this->isAdmin()) {
            return in_array($permission, $rolePermissions, true);
        }

        if (AppSetting::read('role_permissions_'.$role) !== null) {
            return in_array($permission, $rolePermissions, true);
        }

        if (is_array($this->permissions)) {
            return in_array($permission, $this->permissions, true);
        }

        return in_array($permission, $rolePermissions, true);
    }

    /** @return Attribute<list<string>, never> */
    protected function effectivePermissions(): Attribute
    {
        return Attribute::get(function (): array {
            if ($this->is_active === false) {
                return [];
            }
            $role = $this->role ?? 'scm';

            // Role Admin selalu mendapatkan hak akses Admin
            if ($this->isAdmin()) {
                return self::getDefaultPermissionsForRole('admin');
            }

            if (AppSetting::read('role_permissions_'.$role) !== null) {
                return self::getDefaultPermissionsForRole($role);
            }

            return $this->permissions ?? self::getDefaultPermissionsForRole($role);
        });
    }

    public function canPerform(string $module, string $action): bool
    {
        return $this->hasPermission($module) && ($action === 'view' || $this->hasPermission($module.'_'.$action));
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isScm(): bool
    {
        return $this->role === 'scm';
    }

    public function isAr(): bool
    {
        return $this->role === 'ar';
    }

    public function isTelemarketing(): bool
    {
        return $this->role === 'telemarketing';
    }

    public function isPajak(): bool
    {
        return $this->role === 'pajak';
    }

    /**
     * SCM dan Admin bisa melihat semua menu dan data
     */
    public function canAccessAll(): bool
    {
        return in_array($this->role, ['admin', 'scm'], true);
    }

    /**
     * Whether the user may change data on the web (add, edit, delete, import, send, generate):
     * only active Admin and SCM. AR and Telemarketing only view. A user without a role counts as SCM.
     */
    public function canEdit(): bool
    {
        return $this->is_active !== false && in_array($this->role ?? 'scm', ['admin', 'scm'], true);
    }
}

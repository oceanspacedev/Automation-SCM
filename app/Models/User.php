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
#[Appends(['avatar_url'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Daftar seluruh permission yang tersedia di aplikasi
     */
    public const AVAILABLE_PERMISSIONS = [
        'drafts' => 'Draft',
        'invoices' => 'Invoice',
        'form_program' => 'Form Program',
        'data_program' => 'Data Program',
        'riwayat_program' => 'Riwayat Program',
        'email_logs' => 'Riwayat Email',
        'dashboard' => 'Dashboard',
        'master_program' => 'Master Program',
        'master_dealer' => 'Master Dealer',
        'master_customer' => 'Master Customer',
        'master_user' => 'Master User',
    ];

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
        return match ($role) {
            'admin', 'scm' => [
                'drafts',
                'invoices',
                'form_program',
                'data_program',
                'riwayat_program',
                'email_logs',
                'dashboard',
                'master_program',
                'master_dealer',
                'master_customer',
                'master_user',
            ],
            'ar' => [
                'drafts',
                'invoices',
                'riwayat_program',
                'dashboard',
            ],
            'telemarketing' => [
                'form_program',
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
        if (! $this->is_active) {
            return false;
        }

        if (is_array($this->permissions) && count($this->permissions) > 0) {
            return in_array($permission, $this->permissions, true);
        }

        $defaults = self::getDefaultPermissionsForRole($this->role ?? 'scm');

        return in_array($permission, $defaults, true);
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

    /**
     * SCM dan Admin bisa melihat semua menu dan data
     */
    public function canAccessAll(): bool
    {
        return in_array($this->role, ['admin', 'scm'], true);
    }
}

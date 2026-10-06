<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'whatsapp', 'role', 'permissions', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
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

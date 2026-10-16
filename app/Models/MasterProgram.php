<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Master Program: satu daftar untuk semua area (form Jawa Barat & Jawa Tengah memakai program yang sama).
 */
class MasterProgram extends Model
{
    use BelongsToBrand, HasFactory;

    public const CODE_PREFIX = 'PROGRAM';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Normalize a program code, e.g. "program326" / "Program 326" → "PROGRAM 326".
     */
    public static function normalizeCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        if (preg_match('/^PROGRAM\s*(\d+)$/', $code, $matches)) {
            return self::CODE_PREFIX.' '.(int) $matches[1];
        }

        if (ctype_digit($code)) {
            return self::CODE_PREFIX.' '.(int) $code;
        }

        return $code;
    }

    /**
     * Get the next sequential program code (highest "PROGRAM N" + 1).
     */
    public static function nextCode(): string
    {
        $highest = self::query()
            ->where('code', 'like', self::CODE_PREFIX.' %')
            ->pluck('code')
            ->map(fn (string $code): int => preg_match('/^PROGRAM\s+(\d+)$/', $code, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return self::CODE_PREFIX.' '.($highest + 1);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Region (mis. BIG BANDUNG) milik sebuah area (Jawa Barat/Jawa Tengah).
 */
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use BelongsToArea, HasFactory;

    /**
     * Regions merged into another: old name → the region it is now.
     *
     * @var array<string, string>
     */
    public const RENAMED = [
        'BIG KARAWANG' => 'BIG KCPS',
        'BIG CPS' => 'BIG KCPS',
        'BIG CISUKA' => 'BIG KCPS',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The region's current name, e.g. "BIG CPS" → "BIG KCPS"; any other name stays as it is.
     */
    public static function currentName(string $name): string
    {
        return self::RENAMED[strtoupper(trim($name))] ?? $name;
    }
}

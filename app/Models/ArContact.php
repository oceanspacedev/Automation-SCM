<?php

namespace App\Models;

use App\Area;
use App\Models\Concerns\BelongsToArea;
use Database\Factories\ArContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AR sebuah area (Jawa Barat / Jawa Tengah), satu per area: penerima WA "dealer setuju dipotong" dari telemarketing.
 */
class ArContact extends Model
{
    /** @use HasFactory<ArContactFactory> */
    use BelongsToArea, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The active AR of the area (null: the area has none, so the default AR number is used).
     */
    public static function forArea(Area $area): ?self
    {
        return static::withoutGlobalScope('area')
            ->where('area', $area->value)
            ->where('is_active', true)
            ->first();
    }
}

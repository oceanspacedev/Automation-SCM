<?php

namespace App\Models;

use App\Area;
use App\Models\Concerns\BelongsToArea;
use Database\Factories\ArContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AR sebuah region (mis. BIG CIREBON): penerima WA "dealer setuju dipotong" dari telemarketing.
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
     * The active AR of the first given region that has one (e.g. a Data Program's region, then its big region).
     */
    public static function forRegion(Area $area, ?string ...$regions): ?self
    {
        $regions = array_values(array_unique(array_filter(array_map(
            fn (?string $region) => strtoupper(trim((string) $region)),
            $regions
        ), fn (string $region) => $region !== '' && $region !== '-')));

        if ($regions === []) {
            return null;
        }

        $contacts = static::withoutGlobalScope('area')
            ->where('area', $area->value)
            ->where('is_active', true)
            ->whereIn('region', $regions)
            ->get()
            ->keyBy('region');

        foreach ($regions as $region) {
            if ($contact = $contacts->get($region)) {
                return $contact;
            }
        }

        return null;
    }
}

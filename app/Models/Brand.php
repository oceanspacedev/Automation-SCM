<?php

namespace App\Models;

use App\Area;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brand program (Realme, Oppo, ...). Setiap brand punya program, draft, invoice, Form Program & Data Program sendiri.
 */
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    /**
     * Brand of existing data, old links, and Apps Script webhooks without a brand.
     */
    public const DEFAULT_SLUG = 'realme';

    /**
     * Tables whose rows belong to one brand (see BelongsToBrand).
     *
     * @var list<string>
     */
    public const DATA_TABLES = ['drafts', 'invoices', 'data_programs', 'program_submissions', 'program_reconciliation_logs', 'master_programs'];

    protected $guarded = ['id'];

    /**
     * Display name of a brand slug (the slug itself when the brand no longer exists).
     */
    public static function nameFor(?string $slug): string
    {
        $slug ??= self::DEFAULT_SLUG;

        return static::query()->where('slug', $slug)->value('name') ?? ucfirst($slug);
    }

    /**
     * Link where dealers upload a brand's program documents (CN, Agreement, Faktur Pajak) for an area: the brand's
     * public Form Program on the public site, e.g. https://scm-customer.oceanspace.co.id/form/realme/jabar.
     */
    public static function documentUploadUrl(?string $slug, Area $area): string
    {
        $slug ??= self::DEFAULT_SLUG;

        return rtrim((string) config('services.program_form.url'), '/')."/form/{$slug}/{$area->value}";
    }

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @param  Builder<Brand>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Cache key for a brand- and area-specific setting (e.g. spreadsheet links).
     * Realme keeps the area's original key so existing settings stay in place.
     */
    public static function settingKey(string $baseKey, Area $area, string $brand): string
    {
        $key = $area->settingKey($baseKey);

        return $brand === self::DEFAULT_SLUG ? $key : "{$key}_{$brand}";
    }

    /**
     * Whether this is Realme in Jawa Barat, the only scope with built-in spreadsheets and .env links.
     */
    public static function isDefaultScope(Area $area, string $brand): bool
    {
        return $area === Area::default() && $brand === self::DEFAULT_SLUG;
    }

    /**
     * Normalize user input (header, query) to an existing brand slug, or null when invalid/empty.
     */
    public static function slugFromInput(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $slug = strtolower(trim($value));

        return static::query()->where('slug', $slug)->exists() ? $slug : null;
    }

    /**
     * A URL-safe slug for a new brand name that no other brand uses yet (e.g. "Oppo" → "oppo").
     */
    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;

        for ($suffix = 2; static::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * Whether any draft, invoice, Form Program, Data Program, history or program row belongs to this brand.
     */
    public function hasData(): bool
    {
        foreach (self::DATA_TABLES as $table) {
            if (DB::table($table)->where('brand', $this->slug)->exists()) {
                return true;
            }
        }

        return false;
    }
}

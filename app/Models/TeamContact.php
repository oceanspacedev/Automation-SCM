<?php

namespace App\Models;

use Database\Factories\TeamContactFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A member of the tax team (WhatsApp to check the PPh after AR cuts a claim) or the SCM team (WhatsApp with the result
 * of that check), the same for every area. Every active member of a team gets its WhatsApp.
 */
class TeamContact extends Model
{
    /** @use HasFactory<TeamContactFactory> */
    use HasFactory;

    public const TAX = 'tax';

    public const SCM = 'scm';

    /**
     * @var array<string, string>
     */
    public const LABELS = [self::TAX => 'Tim Pajak', self::SCM => 'Tim SCM'];

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The active members of a team, oldest first.
     *
     * @return Collection<int, self>
     */
    public static function activeOf(string $team): Collection
    {
        return static::query()->where('team', $team)->where('is_active', true)->orderBy('id')->get();
    }

    /**
     * Names of a team's active members, e.g. "Reza, Ani"; null when it has none.
     */
    public static function namesOf(string $team): ?string
    {
        return static::activeOf($team)->pluck('name')->implode(', ') ?: null;
    }
}

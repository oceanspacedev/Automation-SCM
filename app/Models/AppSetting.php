<?php

namespace App\Models;

use App\Area;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan aplikasi yang diubah dari aplikasi sendiri (mis. password Reset Data, disimpan sebagai hash).
 */
class AppSetting extends Model
{
    /**
     * Hash of the password asked before Master → Reset Data deletes anything.
     */
    public const DATA_RESET_PASSWORD = 'data_reset_password';

    /**
     * Teams with a name and WhatsApp number per area in Master AR: the tax team checks the PPh of a claim AR has cut,
     * and the SCM team gets the result of that check.
     */
    public const TAX_TEAM = 'tax_team';

    public const SCM_TEAM = 'scm_team';

    /**
     * @var list<string>
     */
    public const TEAMS = [self::TAX_TEAM, self::SCM_TEAM];

    protected $guarded = ['id'];

    public static function read(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value');
    }

    public static function write(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * A team's contact in an area; both are null until set in Master AR.
     *
     * @return array{name: ?string, whatsapp: ?string}
     */
    public static function teamContact(Area $area, string $team): array
    {
        return [
            'name' => static::read($area->settingKey("{$team}_name")) ?: null,
            'whatsapp' => static::read($area->settingKey("{$team}_whatsapp")) ?: null,
        ];
    }

    public static function writeTeamContact(Area $area, string $team, ?string $name, ?string $whatsapp): void
    {
        static::write($area->settingKey("{$team}_name"), $name);
        static::write($area->settingKey("{$team}_whatsapp"), $whatsapp);
    }
}

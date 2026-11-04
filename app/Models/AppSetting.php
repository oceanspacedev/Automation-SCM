<?php

namespace App\Models;

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

    protected $guarded = ['id'];

    public static function read(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value');
    }

    public static function write(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

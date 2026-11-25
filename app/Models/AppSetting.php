<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\CompanyContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan aplikasi yang diubah dari aplikasi sendiri (mis. password Reset Data, disimpan sebagai hash).
 */
class AppSetting extends Model
{
    use BelongsToCompany;

    /**
     * Hash of the password asked before Master → Reset Data deletes anything.
     */
    public const DATA_RESET_PASSWORD = 'data_reset_password';

    protected $guarded = ['id'];

    public static function read(string $key): ?string
    {
        if (str_starts_with($key, 'role_permissions_')) {
            return static::withoutGlobalScope('company')->where('company_id', 1)->where('key', $key)->value('value');
        }

        return static::query()->where('key', $key)->value('value');
    }

    public static function write(string $key, ?string $value): void
    {
        if (str_starts_with($key, 'role_permissions_')) {
            app(CompanyContext::class)->run(1, fn () => static::query()->updateOrCreate(['key' => $key], ['value' => $value]));

            return;
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

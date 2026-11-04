<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Settings keys of the tax & SCM team contacts. Jawa Barat (the default area) used the plain key, which now holds the
     * one contact for every area; Jawa Tengah used the key with "_jateng".
     *
     * @var list<string>
     */
    private const KEYS = ['tax_team_name', 'tax_team_whatsapp', 'scm_team_name', 'scm_team_whatsapp'];

    /**
     * Run the migrations.
     *
     * Tim Pajak & Tim SCM are one contact for all areas: a Jawa Tengah value fills the shared key only when it is still
     * empty, then the Jawa Tengah keys are removed.
     */
    public function up(): void
    {
        foreach (self::KEYS as $key) {
            $jateng = DB::table('app_settings')->where('key', "{$key}_jateng")->value('value');
            $shared = DB::table('app_settings')->where('key', $key)->value('value');

            if (filled($jateng) && blank($shared)) {
                DB::table('app_settings')->updateOrInsert(['key' => $key], ['value' => $jateng, 'updated_at' => now(), 'created_at' => now()]);
            }

            DB::table('app_settings')->where('key', "{$key}_jateng")->delete();
        }
    }

    /**
     * Reverse the migrations: Jawa Tengah gets a copy of the shared contact again.
     */
    public function down(): void
    {
        foreach (self::KEYS as $key) {
            $shared = DB::table('app_settings')->where('key', $key)->value('value');

            if (filled($shared)) {
                DB::table('app_settings')->updateOrInsert(['key' => "{$key}_jateng"], ['value' => $shared, 'updated_at' => now(), 'created_at' => now()]);
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tim Pajak & Tim SCM labels per team, used for a moved contact without a name.
     *
     * @var array<string, string>
     */
    private const TEAMS = ['tax' => 'Tim Pajak', 'scm' => 'Tim SCM'];

    /**
     * Run the migrations.
     *
     * Members of the tax team (WhatsApp to check the PPh after AR cuts a claim) and the SCM team (WhatsApp with the
     * result), the same for every area. The single contact each team had in app_settings becomes its first member.
     */
    public function up(): void
    {
        Schema::create('team_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('team', 20)->index();
            $table->string('name');
            $table->string('whatsapp', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (self::TEAMS as $team => $label) {
            $name = DB::table('app_settings')->where('key', "{$team}_team_name")->value('value');
            $whatsapp = DB::table('app_settings')->where('key', "{$team}_team_whatsapp")->value('value');

            if (filled($whatsapp)) {
                DB::table('team_contacts')->insert([
                    'team' => $team,
                    'name' => filled($name) ? $name : $label,
                    'whatsapp' => $whatsapp,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('app_settings')->whereIn('key', ["{$team}_team_name", "{$team}_team_whatsapp"])->delete();
        }
    }

    /**
     * Reverse the migrations: each team's first active member goes back to app_settings.
     */
    public function down(): void
    {
        foreach (array_keys(self::TEAMS) as $team) {
            $contact = DB::table('team_contacts')->where('team', $team)->where('is_active', true)->orderBy('id')->first();

            if ($contact) {
                foreach (['name' => $contact->name, 'whatsapp' => $contact->whatsapp] as $field => $value) {
                    DB::table('app_settings')->updateOrInsert(['key' => "{$team}_team_{$field}"], ['value' => $value, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        Schema::dropIfExists('team_contacts');
    }
};

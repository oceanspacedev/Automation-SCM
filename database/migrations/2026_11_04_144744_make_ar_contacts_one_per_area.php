<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master AR has one AR per area (Jawa Barat, Jawa Tengah) instead of one per region. Each area keeps the active AR
     * used for the most regions (the regions usually all share one AR); the area's other rows are removed.
     */
    public function up(): void
    {
        DB::table('ar_contacts')->orderBy('id')->get()->groupBy('area')
            ->each(function ($contacts): void {
                $keep = $contacts->filter(fn (object $contact) => (bool) $contact->is_active)
                    ->groupBy('whatsapp')
                    ->sortByDesc(fn ($sameNumber) => $sameNumber->count())
                    ->first()?->first() ?? $contacts->first();

                DB::table('ar_contacts')->where('area', $keep->area)->where('id', '!=', $keep->id)->delete();
            });

        Schema::table('ar_contacts', function (Blueprint $table) {
            $table->dropUnique(['area', 'region']);
        });

        Schema::table('ar_contacts', function (Blueprint $table) {
            $table->dropColumn('region');
            $table->unique('area');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ar_contacts', function (Blueprint $table) {
            $table->dropUnique(['area']);
            $table->string('region', 100)->default('')->after('area');
            $table->unique(['area', 'region']);
        });
    }
};

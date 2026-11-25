<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seed default Tim AP contact (Widiya) for existing non-test environments when none exists yet.
     */
    public function up(): void
    {
        if (app()->runningUnitTests() || ! Schema::hasTable('team_contacts')) {
            return;
        }

        $hasCompanyColumn = Schema::hasColumn('team_contacts', 'company_id');
        $query = DB::table('team_contacts')->where('team', 'ap');
        if ($hasCompanyColumn) {
            if (! DB::table('companies')->where('id', 1)->exists()) {
                return;
            }
            $query->where('company_id', 1);
        }

        if ($query->exists()) {
            return;
        }

        $record = [
            'team' => 'ap',
            'name' => 'Widiya',
            'whatsapp' => '081224290502',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($hasCompanyColumn) {
            $record['company_id'] = 1;
        }

        DB::table('team_contacts')->insert($record);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('team_contacts')) {
            return;
        }

        DB::table('team_contacts')
            ->where('team', 'ap')
            ->where('name', 'Widiya')
            ->where('whatsapp', '081224290502')
            ->delete();
    }
};

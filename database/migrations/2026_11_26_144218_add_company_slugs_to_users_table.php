<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('company_slugs')->nullable()->after('permissions');
        });

        $adminCompanies = json_encode(['top', 'msi'], JSON_THROW_ON_ERROR);
        $topOnly = json_encode(['top'], JSON_THROW_ON_ERROR);

        DB::table('users')->where('role', 'admin')->update(['company_slugs' => $adminCompanies]);
        DB::table('users')->where(function ($query): void {
            $query->where('role', '!=', 'admin')->orWhereNull('role');
        })->update(['company_slugs' => $topOnly]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('company_slugs');
        });
    }
};

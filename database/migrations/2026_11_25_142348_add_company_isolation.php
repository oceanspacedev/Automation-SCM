<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'brands', 'dealers', 'customers', 'regions', 'ar_contacts', 'team_contacts',
        'master_programs', 'drafts', 'invoices', 'data_programs', 'program_submissions',
        'program_reconciliation_logs', 'program_approval_logs', 'email_logs',
        'whatsapp_logs', 'email_accounts', 'google_tokens', 'app_settings', 'automation_schedules',
    ];

    private const UNIQUE_KEYS = [
        'brands' => [['slug'], ['name']],
        'dealers' => [['kode_bt']],
        'customers' => [['code']],
        'regions' => [['area', 'name']],
        'ar_contacts' => [['area']],
        'master_programs' => [['brand', 'name'], ['brand', 'code']],
        'invoices' => [['invoice_number']],
        'data_programs' => [['brand', 'area', 'row_hash']],
        'program_submissions' => [['brand', 'area', 'row_hash']],
        'email_accounts' => [['email']],
        'app_settings' => [['key']],
        'automation_schedules' => [['key']],
    ];

    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->string('default_brand', 50);
            $table->string('invoice_template')->default('standard');
            $table->timestamps();
        });

        DB::table('companies')->insert([
            ['id' => 1, 'slug' => 'top', 'name' => 'CV TOP SELULAR', 'default_brand' => 'realme', 'invoice_template' => 'standard', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => 'msi', 'name' => 'PT MSI', 'default_brand' => 'itel', 'invoice_template' => 'standard', 'created_at' => now(), 'updated_at' => now()],
        ]);

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('company_id')->default(1)->constrained('companies')->restrictOnDelete();
            });
            foreach (self::UNIQUE_KEYS[$name] ?? [] as $columns) {
                Schema::table($name, function (Blueprint $table) use ($columns): void {
                    $table->dropUnique($columns);
                    $table->unique(['company_id', ...$columns]);
                });
            }
        }

        foreach (['ITEL', 'TECNO', 'VILLAON', 'MOTOROLA', 'MOTOPAD'] as $order => $name) {
            DB::table('brands')->insert([
                'company_id' => 2, 'slug' => strtolower($name), 'name' => $name,
                'sort_order' => $order, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (DB::table('automation_schedules')->where('company_id', 1)->get() as $schedule) {
            $values = (array) $schedule;
            unset($values['id']);
            DB::table('automation_schedules')->insert([
                ...$values, 'company_id' => 2, 'is_active' => false, 'last_run_at' => null, 'last_result' => null,
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (DB::table($name)->where('company_id', '!=', 1)->exists()) {
                throw new RuntimeException('Rollback requires archiving and removing the other company data first.');
            }
        }
        foreach (array_reverse(self::TABLES) as $name) {
            foreach (self::UNIQUE_KEYS[$name] ?? [] as $columns) {
                Schema::table($name, function (Blueprint $table) use ($columns): void {
                    $table->dropUnique(['company_id', ...$columns]);
                    $table->unique($columns);
                });
            }
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('company_id');
            });
        }
        Schema::dropIfExists('companies');
    }
};

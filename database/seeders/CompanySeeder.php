<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->firstOrCreate(['slug' => 'top'], ['name' => 'CV TOP SELULAR', 'default_brand' => 'realme']);
        Company::query()->firstOrCreate(['slug' => 'msi'], ['name' => 'PT MSI', 'default_brand' => 'itel']);
    }
}

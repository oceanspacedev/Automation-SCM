<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Initial Master Customers
        $initialCustomers = [
            [
                'code' => 'CV TOP',
                'name' => 'CV TOP SELULAR',
                'npwp' => '31.352.339.1-426.000',
                'npwp_name' => 'CV TOP SELULAR',
                'address' => "Pertatean Blok - No. 011 RT. 001 RW. 005 Kel. Pekalipan\nPekalipan - Kota Cirebon, Jawa Barat 45117",
            ],
            [
                'code' => 'PT RISM',
                'name' => 'PT RETAIL INDONESIA SELALU MAJU',
                'npwp' => '61.186.183.2-044.000',
                'npwp_name' => 'PT RETAIL INDONESIA SELALU MAJU',
                'address' => 'RUKAN MANGGA DUA SQUARE H-18 ANCOL PADEMANGAN JAKARTA',
            ],
            [
                'code' => 'PT MSI',
                'name' => 'PT MEDIA SELULER INDONESIA',
                'npwp' => '01.555.666.7-011.000',
                'npwp_name' => 'PT MEDIA SELULER INDONESIA',
                'address' => 'Jl. Raya Cirebon - Bandung No.109, Kertawinangun, Kec. Kedawung, Kabupaten Cirebon, Jawa Barat 45153',
            ],
            [
                'code' => 'CV SEGAR INDAH',
                'name' => 'CV SEGAR INDAH',
                'npwp' => '02.456.789.1-426.000',
                'npwp_name' => 'CV SEGAR INDAH',
                'address' => 'Jl. Raya Sunan Gunung Jati No. 88, Cirebon, Jawa Barat',
            ],
            [
                'code' => 'CV MITRA ABADI',
                'name' => 'CV MITRA ABADI',
                'npwp' => '02.789.123.4-521.000',
                'npwp_name' => 'CV MITRA ABADI',
                'address' => 'Jl. Diponegoro No. 88, Purwokerto, Jawa Tengah',
            ],
            [
                'code' => 'VOKO CELL',
                'name' => 'VOKO CELL',
                'npwp' => '03.111.222.3-501.000',
                'npwp_name' => 'VOKO CELL',
                'address' => 'Jl. Veteran No. 15, Pekalongan, Jawa Tengah',
            ],
            [
                'code' => 'CV LIA CELLINDO',
                'name' => 'CV LIA CELLINDO',
                'npwp' => '02.333.444.5-502.000',
                'npwp_name' => 'CV LIA CELLINDO',
                'address' => 'Jl. Ahmad Yani No. 102, Tegal, Jawa Tengah',
            ],
        ];

        foreach ($initialCustomers as $cust) {
            Customer::updateOrCreate(
                ['code' => $cust['code']],
                $cust
            );
        }

        // Master Dealer diisi dari file master CV TOP: php artisan dealers:import "<file.xlsx>"
    }
}

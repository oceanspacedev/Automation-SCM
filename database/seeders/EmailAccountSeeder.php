<?php

namespace Database\Seeders;

use App\Models\EmailAccount;
use Illuminate\Database\Seeder;

class EmailAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Rebate. MSI',
                'email' => 'ade@mediaselularindonesia.com',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Program CS',
                'email' => 'admin.scm@completeselular.com',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Program MSI',
                'email' => 'admin.scm@mediaselularindonesia.com',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Program SMI',
                'email' => 'admin.scm@satumediaindonesia.com',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Program Top',
                'email' => 'admin.scm@topselular.com',
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $account) {
            EmailAccount::updateOrCreate(
                ['email' => $account['email']],
                $account
            );
        }
    }
}

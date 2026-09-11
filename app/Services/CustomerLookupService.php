<?php

namespace App\Services;

class CustomerLookupService
{
    /**
     * Master registry for Customer lookup (Bill To party in invoices).
     */
    protected static array $customers = [
        'CV TOP SELULAR' => [
            'name' => 'CV TOP SELULAR',
            'address' => "Pertatean Blok - No. 011 RT. 001 RW. 005 Kel. Pekalipan\nPekaliapan - Kota Cirebon, Jawa Barat 45117",
            'npwp' => '31.352.339.1-426.000',
        ],
        'CV SEGAR INDAH' => [
            'name' => 'CV SEGAR INDAH',
            'address' => "Jl. Raya Sunan Gunung Jati No. 88, Cirebon, Jawa Barat",
            'npwp' => '02.456.789.1-426.000',
        ],
        'PT MITRA TELEKOMUNIKASI SELULAR' => [
            'name' => 'PT MITRA TELEKOMUNIKASI SELULAR',
            'address' => "Gedung Telkom Landmark Tower Lt. 12, Jl. Gatot Subroto Kav. 52, Jakarta Selatan",
            'npwp' => '01.555.666.7-011.000',
        ],
        'CV MITRA ABADI' => [
            'name' => 'CV MITRA ABADI',
            'address' => "Jl. Diponegoro No. 88, Purwokerto, Jawa Tengah",
            'npwp' => '02.789.123.4-521.000',
        ],
        'VOKO CELL' => [
            'name' => 'VOKO CELL',
            'address' => "Jl. Veteran No. 15, Pekalongan, Jawa Tengah",
            'npwp' => '03.111.222.3-501.000',
        ],
        'CV LIA CELLINDO' => [
            'name' => 'CV LIA CELLINDO',
            'address' => "Jl. Ahmad Yani No. 102, Tegal, Jawa Tengah",
            'npwp' => '02.333.444.5-502.000',
        ],
    ];

    /**
     * Lookup customer information by Customer Name.
     */
    public static function lookup(?string $customerName): array
    {
        $normalized = strtoupper(trim((string) $customerName));
        if (empty($normalized)) {
            return [
                'name' => '-',
                'address' => '-',
                'npwp' => '-',
            ];
        }

        foreach (self::$customers as $key => $info) {
            if (strtoupper($key) === $normalized || str_contains($normalized, strtoupper($key)) || str_contains(strtoupper($key), $normalized)) {
                return $info;
            }
        }

        return [
            'name' => $customerName,
            'address' => '-',
            'npwp' => '-',
        ];
    }
}

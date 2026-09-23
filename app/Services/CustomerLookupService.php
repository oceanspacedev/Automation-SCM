<?php

namespace App\Services;

class CustomerLookupService
{
    /**
     * Master registry for standard Bill To selections.
     */
    protected static array $billToOptions = [
        'CV TOP' => [
            'code' => 'CV TOP',
            'name' => 'CV TOP SELULAR',
            'address' => "Pertatean Blok - No. 011 RT. 001 RW. 005 Kel. Pekalipan\nPekaliapan - Kota Cirebon, Jawa Barat 45117",
            'npwp' => '31.352.339.1-426.000',
        ],
        'PT RISM' => [
            'code' => 'PT RISM',
            'name' => 'PT RETAIL INDONESIA SELALU MAJU',
            'address' => 'RUKAN MANGGA DUA SQUARE H-18 ANCOL PADEMANGAN JAK',
            'npwp' => '61.186.183.2-044.000',
        ],
        'PT MSI' => [
            'code' => 'PT MSI',
            'name' => 'PT MEDIA SELULER INDONESIA',
            'address' => 'Jl. Raya Cirebon - Bandung No.109, Kertawinangun, Kec. Kedawung, Kabupaten Cirebon, Jawa Barat 45153',
            'npwp' => '01.555.666.7-011.000',
        ],
    ];

    /**
     * Master registry for Customer lookup (Bill To party in invoices).
     */
    protected static array $customers = [
        'CV TOP' => [
            'name' => 'CV TOP SELULAR',
            'address' => "Pertatean Blok - No. 011 RT. 001 RW. 005 Kel. Pekalipan\nPekaliapan - Kota Cirebon, Jawa Barat 45117",
            'npwp' => '31.352.339.1-426.000',
        ],
        'CV TOP SELULAR' => [
            'name' => 'CV TOP SELULAR',
            'address' => "Pertatean Blok - No. 011 RT. 001 RW. 005 Kel. Pekalipan\nPekaliapan - Kota Cirebon, Jawa Barat 45117",
            'npwp' => '31.352.339.1-426.000',
        ],
        'PT RISM' => [
            'name' => 'PT RETAIL INDONESIA SELALU MAJU',
            'address' => 'RUKAN MANGGA DUA SQUARE H-18 ANCOL PADEMANGAN JAK',
            'npwp' => '61.186.183.2-044.000',
        ],
        'PT RETAIL INDONESIA SELALU MAJU' => [
            'name' => 'PT RETAIL INDONESIA SELALU MAJU',
            'address' => 'RUKAN MANGGA DUA SQUARE H-18 ANCOL PADEMANGAN JAK',
            'npwp' => '61.186.183.2-044.000',
        ],
        'PT MSI' => [
            'name' => 'PT MEDIA SELULER INDONESIA',
            'address' => 'Jl. Raya Cirebon - Bandung No.109, Kertawinangun, Kec. Kedawung, Kabupaten Cirebon, Jawa Barat 45153',
            'npwp' => '01.555.666.7-011.000',
        ],
        'PT MEDIA SELULER INDONESIA' => [
            'name' => 'PT MEDIA SELULER INDONESIA',
            'address' => 'Jl. Raya Cirebon - Bandung No.109, Kertawinangun, Kec. Kedawung, Kabupaten Cirebon, Jawa Barat 45153',
            'npwp' => '01.555.666.7-011.000',
        ],
        'CV SEGAR INDAH' => [
            'name' => 'CV SEGAR INDAH',
            'address' => 'Jl. Raya Sunan Gunung Jati No. 88, Cirebon, Jawa Barat',
            'npwp' => '02.456.789.1-426.000',
        ],
        'PT MITRA TELEKOMUNIKASI SELULAR' => [
            'name' => 'PT MEDIA SELULER INDONESIA',
            'address' => 'Jl. Raya Cirebon - Bandung No.109, Kertawinangun, Kec. Kedawung, Kabupaten Cirebon, Jawa Barat 45153',
            'npwp' => '01.555.666.7-011.000',
        ],
        'CV MITRA ABADI' => [
            'name' => 'CV MITRA ABADI',
            'address' => 'Jl. Diponegoro No. 88, Purwokerto, Jawa Tengah',
            'npwp' => '02.789.123.4-521.000',
        ],
        'VOKO CELL' => [
            'name' => 'VOKO CELL',
            'address' => 'Jl. Veteran No. 15, Pekalongan, Jawa Tengah',
            'npwp' => '03.111.222.3-501.000',
        ],
        'CV LIA CELLINDO' => [
            'name' => 'CV LIA CELLINDO',
            'address' => 'Jl. Ahmad Yani No. 102, Tegal, Jawa Tengah',
            'npwp' => '02.333.444.5-502.000',
        ],
    ];

    /**
     * Get all available standard Bill To options.
     */
    public static function getBillToOptions(): array
    {
        return array_values(self::$billToOptions);
    }

    /**
     * Get specific Bill To data by key (e.g. 'CV TOP', 'PT RISM', 'PT MSI').
     */
    public static function getBillTo(?string $key): ?array
    {
        if (! $key) {
            return null;
        }

        $normalized = strtoupper(trim($key));
        if (isset(self::$billToOptions[$normalized])) {
            return self::$billToOptions[$normalized];
        }

        foreach (self::$billToOptions as $optKey => $opt) {
            if ($optKey === $normalized || strtoupper($opt['name']) === $normalized) {
                return $opt;
            }
        }

        return null;
    }

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

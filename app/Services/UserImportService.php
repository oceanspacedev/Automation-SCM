<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Creates user accounts from an Excel file with each person's name and WhatsApp number, plus an optional email and
 * role (Peran): a plain list (Nama, No WA, Email, Peran) or the Master Customer CV TOP file (TELEMARKETING,
 * NO. WA TELEMARKETING; one row per store). Each number becomes one active account that signs in with WhatsApp OTP,
 * or with its name and the initial password when one is given.
 */
class UserImportService
{
    /**
     * Header names per field in order of preference, compared in lower case without dots and extra spaces.
     *
     * @var array<string, list<string>>
     */
    private const HEADERS = [
        'name' => ['nama', 'name', 'nama pengguna', 'nama user', 'telemarketing', 'nama telemarketing', 'nama tele', 'tele'],
        'whatsapp' => ['no wa', 'no whatsapp', 'whatsapp', 'nomor wa', 'nomor whatsapp', 'no wa telemarketing', 'no telemarketing', 'wa telemarketing', 'no hp telemarketing', 'no hp', 'no telp', 'no'],
        'email' => ['email', 'email pengguna', 'email telemarketing', 'email tele'],
        'role' => ['peran', 'role'],
        'companies' => ['perusahaan', 'company', 'akses perusahaan'],
    ];

    /** Columns of a store file (CV TOP / Master Dealer), whose "Email" column holds the store's email, not the person's. */
    private const STORE_HEADERS = ['id customer', 'kode bt', 'nama customer', 'dealer name'];

    /**
     * Values of the Peran column per role.
     *
     * @var array<string, string>
     */
    private const ROLE_VALUES = [
        'admin' => 'admin',
        'scm' => 'scm',
        'ar' => 'ar',
        'telemarketing' => 'telemarketing',
        'tele' => 'telemarketing',
        'pajak' => 'pajak',
        'tax' => 'pajak',
    ];

    /**
     * @param  string  $defaultRole  role of the rows without a Peran value
     * @param  bool  $canCreateAdmin  whether rows with the Admin role may be created (only an Admin may)
     * @param  list<string>  $defaultCompanySlugs  companies assigned to rows without a Perusahaan column
     * @param  list<string>|null  $allowedCompanySlugs  when non-null, restrict rows to these company slugs
     * @return array{
     *     total_numbers: int,
     *     created: list<array{name: string, whatsapp: string, email: ?string, role: string, company_slugs: list<string>}>,
     *     existing: list<array{name: string, whatsapp: string, account: string}>,
     *     skipped: list<array{row: int, name: string, whatsapp: string, reason: string}>
     * }
     */
    public function import(
        string $path,
        ?string $password = null,
        string $defaultRole = 'telemarketing',
        bool $canCreateAdmin = false,
        array $defaultCompanySlugs = ['top'],
        ?array $allowedCompanySlugs = null,
    ): array {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $rows = $reader->load($path)->getActiveSheet()->toArray(null, true, false, false);
        [$headerIndex, $columns] = $this->findHeader($rows);
        $defaultCompanies = $this->normalizeCompanies($defaultCompanySlugs) ?? ['top'];

        $people = [];
        $skipped = [];

        foreach (array_slice($rows, $headerIndex + 1, preserve_keys: true) as $index => $row) {
            $name = Str::squish((string) ($row[$columns['name']] ?? ''));
            $rawPhone = trim($this->cellText($row[$columns['whatsapp']] ?? null));

            if ($name === '' && $rawPhone === '') {
                continue;
            }

            $phone = User::normalizeWhatsapp($rawPhone);
            $rawRole = isset($columns['role']) ? strtolower(trim((string) ($row[$columns['role']] ?? ''))) : '';
            $role = $rawRole === '' ? $defaultRole : (self::ROLE_VALUES[$rawRole] ?? null);
            $rawCompanies = isset($columns['companies']) ? trim((string) ($row[$columns['companies']] ?? '')) : '';
            $companySlugs = $rawCompanies === '' ? $defaultCompanies : $this->parseCompaniesCell($rawCompanies);

            $reason = match (true) {
                $name === '' => 'Nama kosong.',
                $rawPhone === '' => 'Nomor WhatsApp kosong.',
                ! preg_match('/^628\d{7,12}$/', (string) $phone) => 'Nomor WhatsApp tidak valid.',
                $role === null => 'Peran tidak dikenal (Admin, SCM, AR, atau Telemarketing).',
                $role === 'admin' && ! $canCreateAdmin => 'Hanya Admin yang bisa membuat akun Admin.',
                $companySlugs === null => 'Perusahaan tidak dikenal (CV TOP, PT MSI, atau Keduanya).',
                $allowedCompanySlugs !== null && array_diff($companySlugs, $allowedCompanySlugs) !== [] => 'Tidak memiliki akses ke perusahaan tersebut.',
                default => null,
            };

            if ($reason !== null) {
                // Satu catatan per orang, walau namanya muncul di banyak baris toko
                $skipped[mb_strtolower($name).'|'.$rawPhone] ??= ['row' => $index + 1, 'name' => $name, 'whatsapp' => $rawPhone, 'reason' => $reason];

                continue;
            }

            $email = isset($columns['email']) ? strtolower(trim((string) ($row[$columns['email']] ?? ''))) : '';
            $people[$phone] ??= ['row' => $index + 1, 'name' => $name, 'role' => $role, 'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null, 'company_slugs' => $companySlugs];
        }

        $users = User::all(['id', 'name', 'email', 'whatsapp']);
        $accountsByPhone = $users->keyBy(fn (User $user) => User::normalizeWhatsapp($user->whatsapp));
        $takenNames = $users->map(fn (User $user) => mb_strtolower($user->name))->flip()->all();
        $takenEmails = $users->pluck('email')->filter()->map(fn (string $email) => strtolower($email))->flip()->all();

        $created = [];
        $existing = [];

        foreach ($people as $phone => $person) {
            if ($account = $accountsByPhone->get($phone)) {
                $existing[] = ['name' => $person['name'], 'whatsapp' => $phone, 'account' => $account->name];

                continue;
            }

            // Nama dipakai untuk login dengan password, jadi tidak boleh sama dengan akun lain
            if (isset($takenNames[mb_strtolower($person['name'])])) {
                $skipped[] = ['row' => $person['row'], 'name' => $person['name'], 'whatsapp' => $phone, 'reason' => 'Nama sudah dipakai akun lain dengan nomor berbeda.'];

                continue;
            }

            // Tanpa email (atau email sudah dipakai akun lain) emailnya dikosongkan
            $email = $person['email'] && ! isset($takenEmails[$person['email']]) ? $person['email'] : null;

            User::create([
                'name' => $person['name'],
                'email' => $email,
                'whatsapp' => $phone,
                'role' => $person['role'],
                'permissions' => $person['role'] === 'admin' ? null : User::getDefaultPermissionsForRole($person['role']),
                'company_slugs' => $person['company_slugs'],
                // Tanpa password awal akun masuk lewat OTP WhatsApp; password acak ini tidak pernah ditampilkan
                'password' => $password ?: Str::random(40),
                'is_active' => true,
            ]);

            $takenNames[mb_strtolower($person['name'])] = true;
            if ($email !== null) {
                $takenEmails[$email] = true;
            }
            $created[] = ['name' => $person['name'], 'whatsapp' => $phone, 'email' => $email, 'role' => $person['role'], 'company_slugs' => $person['company_slugs']];
        }

        return [
            'total_numbers' => count($people),
            'created' => $created,
            'existing' => $existing,
            'skipped' => array_values($skipped),
        ];
    }

    /**
     * Parse a Perusahaan cell such as "CV TOP", "PT MSI", "PT MEDIA SELULAR INDONESIA", or "Keduanya".
     *
     * @return list<string>|null
     */
    private function parseCompaniesCell(string $raw): ?array
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
        if (in_array($normalized, ['keduanya', 'semua', 'both', 'all', 'top & msi', 'top, msi'], true)) {
            return ['top', 'msi'];
        }

        $slugs = [];
        foreach (preg_split('/[,;&+|\/]+/', $normalized) ?: [] as $part) {
            $token = trim($part);
            if ($token === '') {
                continue;
            }
            if (str_contains($token, 'top')) {
                $slugs[] = 'top';
            } elseif (str_contains($token, 'msi') || str_contains($token, 'media selul')) {
                $slugs[] = 'msi';
            } else {
                return null;
            }
        }

        return $this->normalizeCompanies($slugs);
    }

    /**
     * @param  list<string>  $slugs
     * @return list<string>|null
     */
    private function normalizeCompanies(array $slugs): ?array
    {
        $ordered = array_values(array_intersect(['top', 'msi'], $slugs));

        return $ordered !== [] ? $ordered : null;
    }

    /**
     * The header row (within the first rows) and the column index of each field.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}
     */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, preserve_keys: true) as $index => $row) {
            $headers = array_map(fn ($cell) => trim(preg_replace('/\s+/', ' ', str_replace(['.', '_'], ' ', strtolower((string) $cell)))), $row);

            $columns = [];
            foreach (self::HEADERS as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $column = array_search($alias, $headers, true);
                    if ($column !== false && ! in_array($column, $columns, true)) {
                        $columns[$field] = $column;
                        break;
                    }
                }
            }

            if (isset($columns['name'], $columns['whatsapp'])) {
                if (array_intersect(self::STORE_HEADERS, $headers)) {
                    unset($columns['email']);
                }

                return [$index, $columns];
            }
        }

        throw new RuntimeException('Kolom Nama dan No WA (atau TELEMARKETING dan NO. WA TELEMARKETING di file CV TOP) tidak ditemukan di file.');
    }

    /**
     * A cell as text: numbers such as 81224290502 without decimals or exponent.
     */
    private function cellText(mixed $value): string
    {
        return is_float($value) && floor($value) === $value ? sprintf('%.0f', $value) : (string) $value;
    }
}

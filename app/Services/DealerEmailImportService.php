<?php

namespace App\Services;

use App\Area;
use App\Models\Dealer;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Fills the stores' emails in Master Dealer from an Excel file with an Email column and a Kode BT, ID GS or store name
 * column (an exported Master Dealer file works too). Every area is searched. The file decides the email of each store it
 * lists, so importing a newer file updates them; an empty cell keeps the email the store already has.
 *
 * A store name is matched loosely: punctuation is ignored, the alias counts, and a name that contains the other (e.g.
 * "ERA 2000 _ CV SEHAT BAHAGIA SUKSES" and "CV SEHAT BAHAGIA SUKSES") is offered for review. A name shared by several
 * stores, or only resembling some, is left for the user to pick in the preview (see $choices).
 */
class DealerEmailImportService
{
    /**
     * Header names per field in order of preference, compared in lower case without dots and extra spaces.
     *
     * @var array<string, list<string>>
     */
    private const HEADERS = [
        'kode_bt' => ['kode bt', 'kodebt', 'kode_bt', 'id customer', 'kode customer', 'bt'],
        'id_realme' => ['id gs', 'id realme', 'id_realme', 'id real'],
        'dealer_name' => ['nama customer', 'nama dealer', 'nama toko', 'dealer name', 'dealer', 'customer', 'toko', 'nama'],
        'email' => ['email', 'e-mail', 'email dealer', 'email toko', 'email customer', 'alamat email', 'email address'],
    ];

    /** Fits the dealers.email column. */
    private const MAX_LENGTH = 255;

    /** Words too common in store names to suggest a store by. */
    private const COMMON_WORDS = ['CV', 'PT', 'UD', 'TOKO', 'CELL', 'CELLULAR', 'CELULAR', 'SELULAR', 'SELLULAR', 'PONSEL', 'PHONE', 'PHONSEL', 'STORE', 'SHOP', 'GADGET', 'ACC', 'DAN', 'THE'];

    /** Stores offered per row to choose from. */
    private const MAX_CANDIDATES = 8;

    private const MAX_SUGGESTIONS = 5;

    /**
     * Read the file and, unless previewing, save the new emails.
     *
     * @param  array<int, int>  $choices  file row => store id picked in the preview for a row to review (0 skips it)
     * @return array{
     *     columns: array<string, array{header: string, column: string}>,
     *     total_rows: int,
     *     filled: int,
     *     updated: int,
     *     same: int,
     *     rows: list<array{row: int, kode_bt: ?string, dealer_name: ?string, old_email: ?string, new_email: string, status: string}>,
     *     review: list<array{row: int, name: string, email: string, kind: string, selected: ?int, candidates: list<array<string, mixed>>}>,
     *     invalid: list<array{row: int, key: string, email: string}>,
     *     not_found: list<array{row: int, key: string, reason: string}>
     * }
     */
    public function import(string $path, bool $previewOnly = false, array $choices = []): array
    {
        [$fileRows, $totalRows, $columns] = $this->readFile($path);
        $lookup = $this->dealerLookup();

        $invalid = [];
        $notFound = [];
        $review = [];
        // Satu toko boleh muncul di beberapa baris file: semua emailnya digabung
        $emailsByDealer = [];
        $firstRowOf = [];

        foreach ($fileRows as $fileRow) {
            if ($fileRow['emails'] === []) {
                $invalid[] = ['row' => $fileRow['row'], 'key' => $fileRow['key'], 'email' => $fileRow['email_text']];

                continue;
            }

            $match = $this->match($fileRow, $lookup);

            if ($match['kind'] === 'none') {
                $notFound[] = ['row' => $fileRow['row'], 'key' => $fileRow['key'], 'reason' => $match['reason']];

                continue;
            }

            $dealerId = $match['kind'] === 'matched' ? $match['dealer_id'] : null;
            if ($match['kind'] !== 'matched') {
                // Mirip: sudah terpilih tapi bisa diganti; beberapa toko / saran: menunggu dipilih
                $dealerId = array_key_exists($fileRow['row'], $choices)
                    ? (in_array((int) $choices[$fileRow['row']], $match['candidates'], true) ? (int) $choices[$fileRow['row']] : null)
                    : ($match['kind'] === 'similar' ? $match['candidates'][0] : null);

                $review[] = [
                    'row' => $fileRow['row'],
                    'name' => $fileRow['dealer_name'] ?? $fileRow['key'],
                    'email' => implode(', ', $fileRow['emails']),
                    'kind' => $match['kind'],
                    'selected' => $dealerId,
                    'candidates' => array_map(fn (int $id) => $this->summary($lookup['by_id'][$id]), $match['candidates']),
                ];
            }

            if ($dealerId === null) {
                continue;
            }

            $emailsByDealer[$dealerId] = array_values(array_unique([...($emailsByDealer[$dealerId] ?? []), ...$fileRow['emails']]));
            $firstRowOf[$dealerId] ??= $fileRow['row'];
        }

        $counts = ['filled' => 0, 'updated' => 0, 'same' => 0];
        $rows = [];

        foreach ($emailsByDealer as $dealerId => $emails) {
            $dealer = $lookup['by_id'][$dealerId];
            $newEmail = $this->joinEmails($emails);
            $oldEmail = filled($dealer->email) ? $dealer->email : null;

            $status = match (true) {
                $oldEmail === null => 'filled',
                $this->sameEmails($oldEmail, $newEmail) => 'same',
                default => 'updated',
            };
            $counts[$status]++;

            $rows[] = [
                'row' => $firstRowOf[$dealerId],
                'kode_bt' => $dealer->kode_bt,
                'dealer_name' => $dealer->dealer_name,
                'old_email' => $oldEmail,
                'new_email' => $newEmail,
                'status' => $status,
            ];

            if (! $previewOnly && $status !== 'same') {
                $dealer->forceFill(['email' => $newEmail])->save();
            }
        }

        usort($rows, fn (array $a, array $b) => $a['row'] <=> $b['row']);

        return [
            'columns' => $columns,
            'total_rows' => $totalRows,
            ...$counts,
            'rows' => $rows,
            'review' => $review,
            'invalid' => $invalid,
            'not_found' => $notFound,
        ];
    }

    /**
     * The rows of the first sheet that has an Email column and a column naming the store, and which column each field
     * was read from (shown in the preview).
     *
     * @return array{0: list<array{row: int, kode_bt: ?string, id_realme: ?string, dealer_name: ?string, key: string, email_text: string, emails: list<string>}>, 1: int, 2: array<string, array{header: string, column: string}>}
     */
    private function readFile(string $path): array
    {
        foreach (IOFactory::load($path)->getAllSheets() as $worksheet) {
            $rows = $worksheet->toArray(null, true, false, false);
            $header = $this->findHeader($rows);

            if ($header === null) {
                continue;
            }

            [$headerIndex, $columns] = $header;
            $fileRows = [];
            $totalRows = 0;
            $columnInfo = array_map(fn (int $column) => [
                'header' => trim((string) ($rows[$headerIndex][$column] ?? '')),
                'column' => Coordinate::stringFromColumnIndex($column + 1),
            ], $columns);

            foreach (array_slice($rows, $headerIndex + 1, preserve_keys: true) as $index => $row) {
                $values = [];
                foreach (['kode_bt', 'id_realme', 'dealer_name'] as $field) {
                    $values[$field] = isset($columns[$field]) ? $this->cellText($row[$columns[$field]] ?? null) : null;
                }
                // Kode ditulis tanpa spasi dan huruf besar ("bt 05165" → "BT05165")
                foreach (['kode_bt', 'id_realme'] as $field) {
                    $values[$field] = $values[$field] === null ? null : strtoupper(preg_replace('/\s+/', '', $values[$field]));
                }
                $emailText = (string) $this->cellText($row[$columns['email']] ?? null);

                if (array_filter($values) === []) {
                    continue;
                }

                $totalRows++;

                // Sel email kosong: email toko yang sudah ada tidak diubah
                if ($emailText === '') {
                    continue;
                }

                $fileRows[] = [
                    'row' => $index + 1,
                    ...$values,
                    'key' => implode(' · ', array_filter([$values['kode_bt'] ?? $values['id_realme'], $values['dealer_name']])),
                    'email_text' => $emailText,
                    'emails' => $this->emailsIn($emailText),
                ];
            }

            return [$fileRows, $totalRows, $columnInfo];
        }

        throw new RuntimeException('Kolom Email dan Kode BT (atau ID GS / Nama Dealer) tidak ditemukan di file.');
    }

    /**
     * The header row (within the first 10 rows) and the column of each field it has: by header name, a header that
     * only contains the name (e.g. "KODE BT DEALER", "EMAIL TOKO"), and for Kode BT & ID GS also the values below it.
     *
     * @param  list<list<mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}|null
     */
    private function findHeader(array $rows): ?array
    {
        foreach (array_slice($rows, 0, 10, preserve_keys: true) as $index => $row) {
            $headers = array_map(fn ($cell) => trim(preg_replace('/\s+/', ' ', str_replace(['.', '_'], ' ', strtolower((string) $cell)))), $row);

            $columns = [];
            foreach (self::HEADERS as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $column = array_search(str_replace('_', ' ', $alias), $headers, true);
                    if ($column !== false && ! in_array($column, $columns, true)) {
                        $columns[$field] = $column;
                        break;
                    }
                }
            }

            foreach ($headers as $column => $header) {
                if ($header === '' || in_array($column, $columns, true)) {
                    continue;
                }

                $field = match (true) {
                    ! isset($columns['email']) && str_contains($header, 'mail') => 'email',
                    ! isset($columns['kode_bt']) && preg_match('/\bbt\b/', $header) && ! str_contains($header, 'nama') => 'kode_bt',
                    ! isset($columns['id_realme']) && preg_match('/\b(gs|realme)\b/', $header) && ! str_contains($header, 'nama') => 'id_realme',
                    ! isset($columns['dealer_name']) && preg_match('/\b(nama|dealer|toko|customer|outlet)\b/', $header) => 'dealer_name',
                    default => null,
                };
                if ($field !== null) {
                    $columns[$field] = $column;
                }
            }

            if (! isset($columns['email'])) {
                continue;
            }

            $columns += $this->codeColumnsByValue(array_slice($rows, $index + 1, 50), $columns);

            if (isset($columns['kode_bt']) || isset($columns['id_realme']) || isset($columns['dealer_name'])) {
                return [$index, $columns];
            }
        }

        return null;
    }

    /**
     * Kode BT and ID GS columns recognized by their values (BT05165, DLWY6135) when the header didn't name them.
     *
     * @param  list<list<mixed>>  $sampleRows
     * @param  array<string, int>  $columns  columns already found
     * @return array<string, int>
     */
    private function codeColumnsByValue(array $sampleRows, array $columns): array
    {
        $patterns = ['kode_bt' => '/^BTO?\d{3,6}[A-Z]*$/', 'id_realme' => '/^DL[A-Z]{2}\d{4}$/'];
        $found = [];

        foreach ($patterns as $field => $pattern) {
            if (isset($columns[$field])) {
                continue;
            }

            foreach (array_keys($sampleRows[0] ?? []) as $column) {
                if (in_array($column, $columns, true) || in_array($column, $found, true)) {
                    continue;
                }

                $values = array_values(array_filter(array_map(
                    fn (array $row) => strtoupper(preg_replace('/\s+/', '', (string) $this->cellText($row[$column] ?? null))),
                    $sampleRows
                )));
                $matching = count(array_filter($values, fn (string $value) => preg_match($pattern, $value) === 1));

                if ($matching >= 2 && $matching >= count($values) * 0.6) {
                    $found[$field] = $column;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Every store once: by id, by kode BT, by ID GS, by normalized name or alias, plus each one's names for the loose
     * name match.
     *
     * @return array{by_id: array<int, Dealer>, by_kode_bt: array<string, Dealer>, by_id_realme: Collection<string, Collection<int, Dealer>>, by_name: array<string, list<int>>, names: array<int, list<string>>}
     */
    private function dealerLookup(): array
    {
        $dealers = Dealer::withoutGlobalScope('area')->get(['id', 'area', 'kode_bt', 'id_realme', 'dealer_name', 'alias', 'gs_name', 'region', 'email']);

        $names = [];
        $byName = [];
        foreach ($dealers as $dealer) {
            $names[$dealer->id] = array_values(array_unique(array_filter(
                [$this->normalizeName($dealer->dealer_name), $this->normalizeName($dealer->alias), $this->normalizeName($dealer->gs_name)],
                fn (string $name) => $name !== '' && $name !== 'BLANK'
            )));
            foreach ($names[$dealer->id] as $name) {
                $byName[$name][] = $dealer->id;
            }
        }

        return [
            'by_id' => $dealers->keyBy('id')->all(),
            'by_kode_bt' => $dealers->filter(fn (Dealer $dealer) => filled($dealer->kode_bt))->keyBy(fn (Dealer $dealer) => strtoupper(trim($dealer->kode_bt)))->all(),
            'by_id_realme' => $dealers->filter(fn (Dealer $dealer) => filled($dealer->id_realme))->groupBy(fn (Dealer $dealer) => strtoupper(trim($dealer->id_realme))),
            'by_name' => array_map(fn (array $ids) => array_values(array_unique($ids)), $byName),
            'names' => $names,
        ];
    }

    /**
     * How a file row matches Master Dealer. A row with a Kode BT ("BTO…" equals "BT0…") or ID GS is matched by that
     * code alone, whatever its name says; when the code isn't in Master Dealer only an identical name still counts.
     * A row with only a name is matched loosely: "matched" picks one store; "similar" (one store whose name contains
     * the other), "choose" (several stores) and "suggest" (stores with a resembling name) are reviewed in the preview;
     * "none" finds nothing.
     *
     * @param  array{kode_bt: ?string, id_realme: ?string, dealer_name: ?string}  $fileRow
     * @return array{kind: string, dealer_id: ?int, candidates: list<int>, reason: string}
     */
    private function match(array $fileRow, array $lookup): array
    {
        $none = fn (string $reason) => ['kind' => 'none', 'dealer_id' => null, 'candidates' => [], 'reason' => $reason];
        $one = fn (int $id) => ['kind' => 'matched', 'dealer_id' => $id, 'candidates' => [$id], 'reason' => ''];
        $name = $this->normalizeName($fileRow['dealer_name']);
        $exact = $name === '' ? [] : ($lookup['by_name'][$name] ?? []);

        if ($fileRow['kode_bt'] !== null) {
            $dealer = $lookup['by_kode_bt'][Dealer::normalizeKodeBt($fileRow['kode_bt'])] ?? $lookup['by_kode_bt'][$fileRow['kode_bt']] ?? null;
            if ($dealer) {
                return $one($dealer->id);
            }
        }

        if ($fileRow['id_realme'] !== null) {
            $ids = $lookup['by_id_realme']->get($fileRow['id_realme'], collect())->pluck('id')->all();
            if (count($ids) > 1 && $exact !== []) {
                $ids = array_values(array_intersect($ids, $exact)) ?: $ids;
            }
            if (count($ids) === 1) {
                return $one($ids[0]);
            }
            if ($ids !== []) {
                return ['kind' => 'choose', 'dealer_id' => null, 'candidates' => $this->sortedCandidates($ids, $lookup), 'reason' => ''];
            }
        }

        // Ada kode tapi tidak ada di Master Dealer: tidak ditebak dari nama mirip
        if ($fileRow['kode_bt'] !== null || $fileRow['id_realme'] !== null) {
            if (count($exact) === 1) {
                return $one($exact[0]);
            }

            return $none($fileRow['kode_bt'] !== null ? 'Kode BT tidak ada di Master Dealer' : 'ID GS tidak ada di Master Dealer');
        }

        $reason = 'Nama dealer tidak ada di Master Dealer';
        if (count($exact) === 1) {
            return $one($exact[0]);
        }
        if ($exact !== []) {
            return ['kind' => 'choose', 'dealer_id' => null, 'candidates' => $this->sortedCandidates($exact, $lookup), 'reason' => ''];
        }

        $containing = $this->containingNames($name, $lookup['names']);
        if (count($containing) === 1) {
            return ['kind' => 'similar', 'dealer_id' => null, 'candidates' => $containing, 'reason' => ''];
        }
        if ($containing !== []) {
            return ['kind' => 'choose', 'dealer_id' => null, 'candidates' => $this->sortedCandidates($containing, $lookup), 'reason' => ''];
        }

        $suggestions = $this->resemblingNames($name, $lookup);

        return $suggestions !== []
            ? ['kind' => 'suggest', 'dealer_id' => null, 'candidates' => $suggestions, 'reason' => '']
            : $none($reason);
    }

    /**
     * Stores whose name or alias contains the file's name, or is contained in it, as whole words of at least two words
     * (e.g. "ADINDA CELL" in "ADINDA CELL GARUT", "CV SEHAT BAHAGIA SUKSES" in "ERA 2000 CV SEHAT BAHAGIA SUKSES").
     *
     * @param  array<int, list<string>>  $names
     * @return list<int>
     */
    private function containingNames(string $name, array $names): array
    {
        if (substr_count($name, ' ') < 1) {
            return [];
        }

        $padded = " {$name} ";
        $ids = [];
        foreach ($names as $id => $dealerNames) {
            foreach ($dealerNames as $dealerName) {
                $contained = substr_count($dealerName, ' ') >= 1 && str_contains($padded, " {$dealerName} ");
                if ($contained || str_contains(" {$dealerName} ", $padded)) {
                    $ids[] = $id;
                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * Stores sharing the most distinctive words with the file's name, a word matching the start of the other
     * (e.g. "LIKE PONSEL" → LIKE PHONE CELL, "FAM STORE" → FAMILY CELL).
     *
     * @return list<int>
     */
    private function resemblingNames(string $name, array $lookup): array
    {
        $words = array_values(array_filter(
            explode(' ', $name),
            fn (string $word) => strlen($word) >= 3 && ! is_numeric($word) && ! in_array($word, self::COMMON_WORDS, true)
        ));
        if ($words === []) {
            return [];
        }

        $scores = [];
        foreach ($lookup['names'] as $id => $dealerNames) {
            $dealerWords = explode(' ', implode(' ', $dealerNames));
            $score = count(array_filter($words, fn (string $word) => collect($dealerWords)->contains(
                fn (string $dealerWord) => str_starts_with($dealerWord, $word) || (strlen($dealerWord) >= 4 && str_starts_with($word, $dealerWord))
            )));
            if ($score > 0) {
                $scores[$id] = $score;
            }
        }

        uksort($scores, fn (int $a, int $b) => [$scores[$b], $lookup['by_id'][$a]->dealer_name] <=> [$scores[$a], $lookup['by_id'][$b]->dealer_name]);

        return array_slice(array_keys($scores), 0, self::MAX_SUGGESTIONS);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sortedCandidates(array $ids, array $lookup): array
    {
        usort($ids, fn (int $a, int $b) => [$lookup['by_id'][$a]->dealer_name, $lookup['by_id'][$a]->kode_bt] <=> [$lookup['by_id'][$b]->dealer_name, $lookup['by_id'][$b]->kode_bt]);

        return array_slice($ids, 0, self::MAX_CANDIDATES);
    }

    /**
     * What the preview shows of a store to choose from.
     *
     * @return array{id: int, kode_bt: ?string, dealer_name: ?string, alias: ?string, region: ?string, area: string, email: ?string}
     */
    private function summary(Dealer $dealer): array
    {
        $alias = trim((string) $dealer->alias);

        return [
            'id' => $dealer->id,
            'kode_bt' => $dealer->kode_bt,
            'dealer_name' => $dealer->dealer_name,
            'alias' => in_array(strtoupper($alias), ['', '-', '(BLANK)'], true) ? null : $alias,
            'region' => $dealer->region,
            'area' => Area::tryFrom((string) $dealer->area)?->label() ?? (string) $dealer->area,
            'email' => $dealer->email,
        ];
    }

    /**
     * Upper case, letters and digits only, single spaces ("CV. MITRA ( WE CELL)" → "CV MITRA WE CELL").
     */
    private function normalizeName(?string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9]+/', ' ', strtoupper((string) $name))));
    }

    /**
     * Valid addresses in a cell, in lower case and without repeats (a cell may list several, e.g. "a@x.com; b@y.com").
     *
     * @return list<string>
     */
    private function emailsIn(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return array_values(array_unique(array_filter(
            array_map(fn (string $email) => strtolower($email), $matches[0]),
            fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        )));
    }

    /**
     * The addresses as one value, as many as fit the column.
     *
     * @param  list<string>  $emails
     */
    private function joinEmails(array $emails): string
    {
        $joined = '';
        foreach ($emails as $email) {
            $next = $joined === '' ? $email : "{$joined}, {$email}";
            if (strlen($next) > self::MAX_LENGTH) {
                break;
            }
            $joined = $next;
        }

        return $joined;
    }

    private function sameEmails(string $old, string $new): bool
    {
        $normalize = fn (string $value) => collect($this->emailsIn($value))->sort()->values()->all();

        return $normalize($old) === $normalize($new);
    }

    private function cellText(mixed $value): ?string
    {
        if (is_float($value) && floor($value) === $value) {
            $value = sprintf('%.0f', $value);
        }

        $text = trim((string) $value);

        return $text === '' || $text === '-' ? null : $text;
    }
}

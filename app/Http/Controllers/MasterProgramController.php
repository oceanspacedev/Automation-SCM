<?php

namespace App\Http\Controllers;

use App\Models\MasterProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MasterProgramController extends Controller
{
    /**
     * Optional import columns mapped to their accepted header names.
     *
     * @var array<string, array<int, string>>
     */
    protected const IMPORT_OPTIONAL_COLUMNS = [
        'period' => ['PERIODE'],
    ];

    /**
     * Display a listing of master programs with search, filter, and stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MasterProgram::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('period', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category = trim((string) $request->input('category'))) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        if ($request->has('is_active') && $request->input('is_active') !== 'all' && $request->input('is_active') !== null) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $programs = $query
            ->orderByRaw('code IS NULL')
            ->orderByRaw('LENGTH(code) DESC')
            ->orderByDesc('code')
            ->orderBy('name')
            ->paginate($perPage);

        $stats = [
            'total' => MasterProgram::count(),
            'active' => MasterProgram::where('is_active', true)->count(),
            'inactive' => MasterProgram::where('is_active', false)->count(),
        ];

        $categories = MasterProgram::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $programs,
            'stats' => $stats,
            'categories' => $categories,
            'next_code' => MasterProgram::nextCode(),
        ]);
    }

    /**
     * Get lightweight list of active programs for dropdown options / datalists.
     */
    public function options(): JsonResponse
    {
        $programs = MasterProgram::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'category', 'period']);

        $names = $programs->pluck('name')->values()->all();

        return response()->json([
            'success' => true,
            'data' => $programs,
            'names' => $names,
        ]);
    }

    /**
     * Store a newly created master program.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge(['code' => MasterProgram::normalizeCode($request->input('code')) ?? MasterProgram::nextCode()]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:master_programs,name'],
            'code' => ['required', 'string', 'max:100', 'unique:master_programs,code'],
            'category' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['name'] = trim($validated['name']);
        if (! empty($validated['category'])) {
            $validated['category'] = strtoupper(trim($validated['category']));
        }
        if (! empty($validated['period'])) {
            $validated['period'] = trim($validated['period']);
        }
        $validated['is_active'] = $validated['is_active'] ?? true;

        $program = MasterProgram::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Master Program berhasil ditambahkan.',
            'data' => $program,
        ], 201);
    }

    /**
     * Update the specified master program.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $program = MasterProgram::findOrFail($id);

        $request->merge(['code' => MasterProgram::normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('master_programs', 'name')->ignore($program->id)],
            'code' => ['nullable', 'string', 'max:100', Rule::unique('master_programs', 'code')->ignore($program->id)],
            'category' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['name'] = trim($validated['name']);
        if (! empty($validated['category'])) {
            $validated['category'] = strtoupper(trim($validated['category']));
        }
        if (! empty($validated['period'])) {
            $validated['period'] = trim($validated['period']);
        }

        $program->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Master Program berhasil diperbarui.',
            'data' => $program,
        ]);
    }

    /**
     * Remove the specified master program.
     */
    public function destroy(int $id): JsonResponse
    {
        $program = MasterProgram::findOrFail($id);
        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'Master Program berhasil dihapus.',
        ]);
    }

    /**
     * Import master programs from an Excel/CSV sheet with columns PROGRAM, NAMA PROGRAM, PERIODE.
     * Rows are matched by program code first, then by program name, and updated in place.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $rows = IOFactory::load($request->file('file')->getRealPath())
            ->getActiveSheet()
            ->toArray(null, true, true, false);

        [$headerIndex, $columns] = $this->detectImportColumns($rows);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $code = MasterProgram::normalizeCode((string) ($row[$columns['code']] ?? ''));
            $name = trim((string) ($row[$columns['name']] ?? ''));

            if ($code === null || $name === '') {
                $skipped++;

                continue;
            }

            $program = MasterProgram::where('code', $code)->first()
                ?? MasterProgram::where('name', $name)->where(fn ($q) => $q->whereNull('code')->orWhere('code', $code))->first();

            if (! $program && MasterProgram::where('name', $name)->exists()) {
                $skipped++;

                continue;
            }

            $program ??= new MasterProgram(['is_active' => true]);
            $program->fill(['code' => $code, 'name' => $name]);

            foreach (array_keys(self::IMPORT_OPTIONAL_COLUMNS) as $attribute) {
                $value = $columns[$attribute] !== null ? trim((string) ($row[$columns[$attribute]] ?? '')) : '';
                if ($value !== '') {
                    $program->{$attribute} = $value;
                }
            }

            $program->exists ? $updated++ : $created++;
            $program->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Import selesai: {$created} program baru, {$updated} diperbarui, {$skipped} baris dilewati.",
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Find the header row and the PROGRAM / NAMA PROGRAM column indexes plus the optional PERIODE column.
     * Falls back to columns A–C in that order when no header row is found.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: int, 1: array{code: int, name: int, period: ?int}}
     */
    protected function detectImportColumns(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $headers = array_map(fn ($cell) => strtoupper(preg_replace('/\s+/', ' ', trim((string) $cell))), $row);
            $codeColumn = array_search('PROGRAM', $headers, true);
            $nameColumn = array_search('NAMA PROGRAM', $headers, true);

            if ($codeColumn !== false && $nameColumn !== false) {
                $columns = ['code' => $codeColumn, 'name' => $nameColumn];

                foreach (self::IMPORT_OPTIONAL_COLUMNS as $attribute => $aliases) {
                    $found = array_values(array_filter(array_map(fn ($alias) => array_search($alias, $headers, true), $aliases), fn ($col) => $col !== false));
                    $columns[$attribute] = $found[0] ?? null;
                }

                return [$index, $columns];
            }
        }

        return [-1, ['code' => 0, 'name' => 1, 'period' => 2]];
    }

    /**
     * Toggle active / inactive status of a master program.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $program = MasterProgram::findOrFail($id);
        $program->is_active = ! $program->is_active;
        $program->save();

        return response()->json([
            'success' => true,
            'message' => 'Status program berhasil diubah menjadi: '.($program->is_active ? 'Aktif' : 'Nonaktif'),
            'data' => $program,
        ]);
    }
}

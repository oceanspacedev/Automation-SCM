<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Services\AreaContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegionController extends Controller
{
    public function __construct(protected AreaContext $areaContext) {}

    /**
     * List regions of the selected area.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Region::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    /**
     * Active region names of the selected area (for dropdowns).
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Region::where('is_active', true)->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $area = $this->areaContext->resolve()->value;
        $request->merge(['name' => strtoupper(trim((string) $request->input('name')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('regions', 'name')->where('area', $area)],
            'is_active' => ['boolean'],
        ]);

        $region = Region::create([
            'area' => $area,
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Region berhasil ditambahkan.',
            'data' => $region,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $region = Region::findOrFail($id);
        $request->merge(['name' => strtoupper(trim((string) $request->input('name')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('regions', 'name')->where('area', $region->area)->ignore($region->id)],
            'is_active' => ['boolean'],
        ]);

        $region->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Region berhasil diperbarui.',
            'data' => $region,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        Region::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Region berhasil dihapus.',
        ]);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $region = Region::findOrFail($id);
        $region->update(['is_active' => ! $region->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Status region berhasil diubah menjadi: '.($region->is_active ? 'Aktif' : 'Nonaktif'),
            'data' => $region,
        ]);
    }
}

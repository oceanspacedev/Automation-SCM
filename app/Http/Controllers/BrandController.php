<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    /**
     * List all brands (menus use the active ones).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Brand::query()->ordered()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateBrand($request);

        $brand = Brand::create([
            ...$validated,
            'slug' => Brand::uniqueSlugFor($validated['name']),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Brand {$brand->name} berhasil ditambahkan.",
            'data' => $brand,
        ], 201);
    }

    /**
     * Rename or reorder a brand. The slug (used in URLs and data) stays the same.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        $brand->update($this->validateBrand($request, $brand->id));

        return response()->json([
            'success' => true,
            'message' => 'Brand berhasil diperbarui.',
            'data' => $brand,
        ]);
    }

    /**
     * Delete a brand that has no data yet; a brand with data can only be deactivated.
     */
    public function destroy(int $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);

        if ($brand->slug === Brand::DEFAULT_SLUG || $brand->hasData()) {
            return response()->json([
                'success' => false,
                'message' => "Brand {$brand->name} sudah punya data dan tidak bisa dihapus. Nonaktifkan saja bila tidak dipakai lagi.",
            ], 422);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brand berhasil dihapus.',
        ]);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);

        if ($brand->is_active && Brand::query()->where('is_active', true)->count() === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal harus ada satu brand aktif.',
            ], 422);
        }

        $brand->update(['is_active' => ! $brand->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Status brand berhasil diubah menjadi: '.($brand->is_active ? 'Aktif' : 'Nonaktif'),
            'data' => $brand,
        ]);
    }

    /**
     * @return array{name: string, sort_order?: int, is_active?: bool}
     */
    private function validateBrand(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($ignoreId)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [
            'name.unique' => 'Brand :input sudah ada.',
        ]);
    }
}

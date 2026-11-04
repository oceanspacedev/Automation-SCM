<?php

namespace App\Http\Controllers;

use App\Area;
use App\Models\ArContact;
use App\Services\SpreadsheetExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Master AR: one AR per area (Jawa Barat, Jawa Tengah), listed together on one page.
 */
class ArContactController extends Controller
{
    /**
     * List the AR of every area.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->filteredQuery($request)->orderBy('area')->get(),
        ]);
    }

    /**
     * Export the AR of every area (with the list's search) to Excel.
     */
    public function export(Request $request, SpreadsheetExport $export): StreamedResponse
    {
        return $export->download(
            'master_ar_'.now()->format('Ymd_His').'.xlsx',
            'Master AR',
            ['AREA', 'NAMA AR', 'NO WHATSAPP', 'STATUS'],
            $this->filteredQuery($request)->orderBy('area')->get()->map(fn (ArContact $arContact) => [
                Area::from($arContact->area)->label(),
                $arContact->name,
                $arContact->whatsapp,
                $arContact->is_active ? 'Aktif' : 'Nonaktif',
            ])
        );
    }

    /**
     * AR of every area matching the list's search.
     *
     * @return Builder<ArContact>
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = ArContact::withoutGlobalScope('area');

        if ($search = trim((string) $request->input('search'))) {
            $areas = collect(Area::cases())->filter(fn (Area $area) => str_contains(strtolower($area->label()), strtolower($search)))->map->value;

            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('whatsapp', 'like', "%{$search}%")
                ->orWhereIn('area', $areas->all()));
        }

        return $query;
    }

    public function store(Request $request): JsonResponse
    {
        $arContact = ArContact::create($this->validateArContact($request));

        return response()->json([
            'success' => true,
            'message' => 'AR berhasil ditambahkan.',
            'data' => $arContact,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $arContact = ArContact::withoutGlobalScope('area')->findOrFail($id);
        $arContact->update($this->validateArContact($request, $arContact->id));

        return response()->json([
            'success' => true,
            'message' => 'AR berhasil diperbarui.',
            'data' => $arContact,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        ArContact::withoutGlobalScope('area')->findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'AR berhasil dihapus.',
        ]);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $arContact = ArContact::withoutGlobalScope('area')->findOrFail($id);
        $arContact->update(['is_active' => ! $arContact->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Status AR berhasil diubah menjadi: '.($arContact->is_active ? 'Aktif' : 'Nonaktif'),
            'data' => $arContact,
        ]);
    }

    /**
     * @return array{area: string, name: string, whatsapp: string, is_active?: bool}
     */
    private function validateArContact(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'area' => strtolower(trim((string) $request->input('area'))),
            'name' => trim((string) $request->input('name')),
            'whatsapp' => trim((string) $request->input('whatsapp')),
        ]);

        return $request->validate([
            'area' => ['required', Rule::enum(Area::class), Rule::unique('ar_contacts', 'area')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s\-]{8,}$/'],
            'is_active' => ['boolean'],
        ], [
            'area.unique' => 'Area ini sudah punya AR. Edit AR area tersebut bila ingin mengganti.',
            'whatsapp.regex' => 'No WhatsApp hanya boleh berisi angka (boleh diawali +62 atau 08).',
        ]);
    }
}

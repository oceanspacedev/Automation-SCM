<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\ArContact;
use App\Services\AreaContext;
use App\Services\SpreadsheetExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArContactController extends Controller
{
    public function __construct(protected AreaContext $areaContext) {}

    /**
     * List the AR of each region in the selected area.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->filteredQuery($request)->orderBy('region')->get(),
        ]);
    }

    /**
     * Export the AR of each region in the selected area (with the list's search) to Excel.
     */
    public function export(Request $request, SpreadsheetExport $export): StreamedResponse
    {
        $area = $this->areaContext->resolve();

        return $export->download(
            'master_ar_'.$area->value.'_'.now()->format('Ymd_His').'.xlsx',
            'Master AR '.$area->label(),
            ['REGION', 'NAMA AR', 'NO WHATSAPP', 'STATUS'],
            $this->filteredQuery($request)->orderBy('region')->get()->map(fn (ArContact $arContact) => [
                $arContact->region,
                $arContact->name,
                $arContact->whatsapp,
                $arContact->is_active ? 'Aktif' : 'Nonaktif',
            ])
        );
    }

    /**
     * AR of the selected area matching the list's search.
     *
     * @return Builder<ArContact>
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = ArContact::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(fn ($q) => $q->where('region', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('whatsapp', 'like', "%{$search}%"));
        }

        return $query;
    }

    public function store(Request $request): JsonResponse
    {
        $area = $this->areaContext->resolve()->value;

        $arContact = ArContact::create([
            'area' => $area,
            ...$this->validateArContact($request, $area),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AR berhasil ditambahkan.',
            'data' => $arContact,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $arContact = ArContact::findOrFail($id);
        $arContact->update($this->validateArContact($request, $arContact->area, $arContact->id));

        return response()->json([
            'success' => true,
            'message' => 'AR berhasil diperbarui.',
            'data' => $arContact,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        ArContact::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'AR berhasil dihapus.',
        ]);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $arContact = ArContact::findOrFail($id);
        $arContact->update(['is_active' => ! $arContact->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Status AR berhasil diubah menjadi: '.($arContact->is_active ? 'Aktif' : 'Nonaktif'),
            'data' => $arContact,
        ]);
    }

    /**
     * The selected area's tax team (WhatsApp to check the PPh after AR cuts a claim) or SCM team (WhatsApp with the
     * result of that check); the route's {team} is "tax" or "scm".
     */
    public function teamContact(string $team): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AppSetting::teamContact($this->areaContext->resolve(), "{$team}_team"),
        ]);
    }

    public function updateTeamContact(Request $request, string $team): JsonResponse
    {
        $area = $this->areaContext->resolve();

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'whatsapp' => trim((string) $request->input('whatsapp')),
        ]);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-]{8,}$/'],
        ], [
            'whatsapp.regex' => 'No WhatsApp hanya boleh berisi angka (boleh diawali +62 atau 08).',
        ]);

        AppSetting::writeTeamContact($area, "{$team}_team", $validated['name'] ?? null, $validated['whatsapp'] ?? null);

        return response()->json([
            'success' => true,
            'message' => ($team === 'scm' ? 'Tim SCM' : 'Tim Pajak').' disimpan.',
            'data' => AppSetting::teamContact($area, "{$team}_team"),
        ]);
    }

    /**
     * @return array{region: string, name: string, whatsapp: string, is_active?: bool}
     */
    private function validateArContact(Request $request, string $area, ?int $ignoreId = null): array
    {
        $request->merge([
            'region' => strtoupper(trim((string) $request->input('region'))),
            'name' => trim((string) $request->input('name')),
            'whatsapp' => trim((string) $request->input('whatsapp')),
        ]);

        return $request->validate([
            'region' => ['required', 'string', 'max:100', Rule::unique('ar_contacts', 'region')->where('area', $area)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s\-]{8,}$/'],
            'is_active' => ['boolean'],
        ], [
            'region.unique' => 'Region :input sudah punya AR. Edit AR region tersebut bila ingin mengganti.',
            'whatsapp.regex' => 'No WhatsApp hanya boleh berisi angka (boleh diawali +62 atau 08).',
        ]);
    }
}

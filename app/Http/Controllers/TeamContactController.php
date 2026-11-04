<?php

namespace App\Http\Controllers;

use App\Models\TeamContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Master Tim Pajak & SCM: members of the tax and SCM teams, one list for every area.
 */
class TeamContactController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => TeamContact::query()->orderByRaw("team = 'scm'")->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $teamContact = TeamContact::create($this->validateTeamContact($request));

        return response()->json([
            'success' => true,
            'message' => TeamContact::LABELS[$teamContact->team].' berhasil ditambahkan.',
            'data' => $teamContact,
        ], 201);
    }

    public function update(Request $request, TeamContact $teamContact): JsonResponse
    {
        $teamContact->update($this->validateTeamContact($request));

        return response()->json([
            'success' => true,
            'message' => TeamContact::LABELS[$teamContact->team].' berhasil diperbarui.',
            'data' => $teamContact,
        ]);
    }

    public function destroy(TeamContact $teamContact): JsonResponse
    {
        $teamContact->delete();

        return response()->json([
            'success' => true,
            'message' => TeamContact::LABELS[$teamContact->team].' berhasil dihapus.',
        ]);
    }

    public function toggleStatus(TeamContact $teamContact): JsonResponse
    {
        $teamContact->update(['is_active' => ! $teamContact->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diubah menjadi: '.($teamContact->is_active ? 'Aktif' : 'Nonaktif'),
            'data' => $teamContact,
        ]);
    }

    /**
     * @return array{team: string, name: string, whatsapp: string, is_active?: bool}
     */
    private function validateTeamContact(Request $request): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'whatsapp' => trim((string) $request->input('whatsapp')),
        ]);

        return $request->validate([
            'team' => ['required', Rule::in(array_keys(TeamContact::LABELS))],
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s\-]{8,}$/'],
            'is_active' => ['boolean'],
        ], [
            'whatsapp.regex' => 'No WhatsApp hanya boleh berisi angka (boleh diawali +62 atau 08).',
        ]);
    }
}

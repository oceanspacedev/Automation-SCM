<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DealerController extends Controller
{
    /**
     * Display a listing of dealers with search, filter, and stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Dealer::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('id_realme', 'like', "%{$search}%")
                    ->orWhere('dealer_name', 'like', "%{$search}%")
                    ->orWhere('kode_bt', 'like', "%{$search}%")
                    ->orWhere('alias', 'like', "%{$search}%")
                    ->orWhere('gs_name', 'like', "%{$search}%")
                    ->orWhere('telemarketing', 'like', "%{$search}%")
                    ->orWhere('sales_person', 'like', "%{$search}%")
                    ->orWhere('pic_name', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        if ($region = trim((string) $request->input('region'))) {
            if ($region !== 'all') {
                $query->where('region', $region);
            }
        }

        if ($request->has('is_active') && $request->input('is_active') !== 'all' && $request->input('is_active') !== null) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $dealers = $query->orderBy('dealer_name')->paginate($perPage);

        $stats = [
            'total' => Dealer::count(),
            'active' => Dealer::where('is_active', true)->count(),
        ];

        $regions = Dealer::whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        return response()->json([
            'success' => true,
            'data' => $dealers,
            'stats' => $stats,
            'regions' => $regions,
        ]);
    }

    /**
     * Lookup dealer for fast autofill in Google Form Program or invoices.
     * Publicly accessible without authentication.
     */
    public function lookup(Request $request): JsonResponse
    {
        $idRealme = trim((string) $request->input('id_realme'));
        $dealerName = trim((string) $request->input('dealer_name'));

        if (empty($idRealme) && empty($dealerName)) {
            return response()->json([
                'success' => false,
                'found' => false,
                'message' => 'Parameter id_realme atau dealer_name diperlukan.',
            ], 400);
        }

        $dealer = null;

        // 1. Exact match on kode BT first (unique), then ID Realme (may be shared by several stores)
        if (! empty($idRealme)) {
            $dealer = Dealer::query()
                ->where('is_active', true)
                ->whereIn('kode_bt', array_unique([Dealer::normalizeKodeBt($idRealme), strtoupper($idRealme)]))
                ->first()
                ?? Dealer::query()
                    ->where('is_active', true)
                    ->where('id_realme', $idRealme)
                    ->first();
        }

        // 2. If not found, try matching by dealer_name
        if (! $dealer && ! empty($dealerName) && $dealerName !== '-') {
            $dealer = Dealer::query()
                ->where('is_active', true)
                ->where(function ($q) use ($dealerName) {
                    $q->where('dealer_name', $dealerName)
                        ->orWhere('dealer_name', 'like', "%{$dealerName}%");
                })
                ->first();
        }

        if (! $dealer) {
            return response()->json([
                'success' => true,
                'found' => false,
                'message' => 'Dealer tidak ditemukan di Master Dealer.',
            ]);
        }

        return response()->json([
            'success' => true,
            'found' => true,
            'data' => [
                'id' => $dealer->id,
                'id_realme' => $dealer->id_realme,
                'dealer_name' => $dealer->dealer_name,
                'kode_bt' => $dealer->kode_bt,
                'region' => $dealer->region,
                'whatsapp' => $dealer->whatsapp,
                'address' => $dealer->address,
                'npwp' => $dealer->npwp,
                'npwp_name' => $dealer->npwp_name,
                'npwp_type' => $dealer->npwp_type,
                'is_pkp' => $dealer->isPkp(),
                'telemarketing' => $dealer->telemarketing,
                'sales_person' => $dealer->sales_person,
            ],
        ]);
    }

    /**
     * Fast autocomplete suggestions for search input.
     */
    public function searchSuggestions(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('query'));
        $region = trim((string) $request->input('region'));
        $limit = min((int) $request->input('limit', 50), 100);

        $query = Dealer::where('is_active', true)
            ->whereNotNull('dealer_name')
            ->whereRaw('LENGTH(TRIM(dealer_name)) >= 2')
            ->whereNotIn('dealer_name', ['.', ',', '-', '/', '..', '...', 'n/a', 'N/A'])
            ->where('dealer_name', 'not like', '.%')
            ->where('dealer_name', 'not like', ',%')
            ->where('dealer_name', 'not like', '-%');

        if (! empty($region) && $region !== 'all') {
            $query->where('region', $region);
        }

        if (! empty($q)) {
            $kodeBt = Dealer::normalizeKodeBt($q);
            $query->where(function ($sub) use ($q, $kodeBt) {
                $sub->where('id_realme', 'like', "%{$q}%")
                    ->orWhere('dealer_name', 'like', "%{$q}%")
                    ->orWhere('alias', 'like', "%{$q}%")
                    ->orWhere('gs_name', 'like', "%{$q}%")
                    ->orWhere('kode_bt', 'like', "%{$q}%")
                    ->orWhere('kode_bt', 'like', "%{$kodeBt}%");
            });
        }

        // Toko tanpa ID Realme (ID GS) memakai kode BT sebagai ID-nya
        $suggestions = $query->orderBy('dealer_name')
            ->limit($limit)
            ->get(['id', 'id_realme', 'dealer_name', 'region', 'kode_bt', 'whatsapp', 'npwp_type'])
            ->map(fn (Dealer $dealer) => [...$dealer->toArray(), 'id_realme' => $dealer->id_realme ?: $dealer->kode_bt, 'is_pkp' => $dealer->isPkp()]);

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    /**
     * Store a newly created dealer.
     */
    public function store(Request $request): JsonResponse
    {
        $dealer = Dealer::create($this->validateDealer($request) + ['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Master Dealer berhasil ditambahkan.',
            'data' => $dealer,
        ], 201);
    }

    /**
     * Update the specified dealer.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $dealer = Dealer::findOrFail($id);
        $dealer->update($this->validateDealer($request, $dealer));

        return response()->json([
            'success' => true,
            'message' => 'Master Dealer berhasil diperbarui.',
            'data' => $dealer,
        ]);
    }

    /**
     * Validate dealer input. Kode BT is the unique key; ID Realme is optional and may be shared by several stores.
     *
     * @return array<string, mixed>
     */
    protected function validateDealer(Request $request, ?Dealer $dealer = null): array
    {
        $request->merge(['kode_bt' => Dealer::normalizeKodeBt($request->input('kode_bt'))]);

        $validated = $request->validate([
            'kode_bt' => ['required', 'string', 'max:100', Rule::unique('dealers', 'kode_bt')->ignore($dealer?->id)],
            'dealer_name' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'id_realme' => ['nullable', 'string', 'max:100'],
            'gs_name' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'npwp_name' => ['nullable', 'string', 'max:255'],
            'npwp_type' => ['nullable', 'string', 'max:50'],
            'reffnote' => ['nullable', 'string', 'max:255'],
            'telemarketing' => ['nullable', 'string', 'max:255'],
            'telemarketing_phone' => ['nullable', 'string', 'max:50'],
            'sales_person' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ], [
            'kode_bt.required' => 'Kode BT wajib diisi.',
            'kode_bt.unique' => 'Kode BT sudah terdaftar di Master Dealer.',
        ]);

        $validated['dealer_name'] = trim($validated['dealer_name']);
        foreach (['id_realme', 'region', 'npwp_type'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = $validated[$field] ? strtoupper(trim($validated[$field])) : null;
            }
        }

        return $validated;
    }

    /**
     * Remove the specified dealer.
     */
    public function destroy(int $id): JsonResponse
    {
        $dealer = Dealer::findOrFail($id);
        $dealer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Master Dealer berhasil dihapus.',
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $dealer = Dealer::findOrFail($id);
        $dealer->is_active = ! $dealer->is_active;
        $dealer->save();

        return response()->json([
            'success' => true,
            'message' => 'Status Dealer berhasil diperbarui.',
            'data' => $dealer,
        ]);
    }
}

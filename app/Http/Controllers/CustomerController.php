<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\SpreadsheetExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers with search, filter, and stats.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $customers = $this->filteredQuery($request)->orderBy('name')->paginate($perPage);

        $stats = [
            'total' => Customer::count(),
            'active' => Customer::where('is_active', true)->count(),
            'inactive' => Customer::where('is_active', false)->count(),
            'with_npwp' => Customer::whereNotNull('npwp')->where('npwp', '!=', '')->where('npwp', '!=', '-')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $customers,
            'stats' => $stats,
        ]);
    }

    /**
     * Export the customers (with the list's search & status filter) to Excel.
     */
    public function export(Request $request, SpreadsheetExport $export): StreamedResponse
    {
        return $export->download(
            'master_customer_'.now()->format('Ymd_His').'.xlsx',
            'Master Customer',
            ['KODE', 'NAMA CUSTOMER', 'NPWP', 'NAMA NPWP', 'ALAMAT', 'STATUS'],
            $this->filteredQuery($request)->orderBy('name')->get()->map(fn (Customer $customer) => [
                $customer->code,
                $customer->name,
                $customer->npwp,
                $customer->npwp_name,
                $customer->address,
                $customer->is_active ? 'Aktif' : 'Nonaktif',
            ])
        );
    }

    /**
     * Customers matching the list's search and status filters.
     *
     * @return Builder<Customer>
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = Customer::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('npwp', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== 'all' && $request->input('is_active') !== null) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        return $query;
    }

    /**
     * Get lightweight list of customers for select options / dropdowns.
     */
    public function options(): JsonResponse
    {
        $customers = Customer::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'npwp', 'address']);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'unique:customers,code'],
            'name' => ['required', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'npwp_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['name'] = trim($validated['name']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $customer = Customer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Master Customer berhasil ditambahkan.',
            'data' => $customer,
        ], 201);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('customers', 'code')->ignore($customer->id)],
            'name' => ['required', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'npwp_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['name'] = trim($validated['name']);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Master Customer berhasil diperbarui.',
            'data' => $customer,
        ]);
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Master Customer berhasil dihapus.',
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->is_active = ! $customer->is_active;
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Status Customer berhasil diperbarui.',
            'data' => $customer,
        ]);
    }
}

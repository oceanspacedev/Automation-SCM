<?php

namespace App\Http\Controllers;

use App\Models\Draft;
use App\Services\InvoiceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    protected InvoiceCalculator $calculator;

    public function __construct(InvoiceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Get paginated draft list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Draft::query()->with('invoice');

        // Search query
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('dealer_code', 'like', "%{$search}%")
                  ->orWhere('dealer_name', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('cn_number', 'like', "%{$search}%")
                  ->orWhere('program_name', 'like', "%{$search}%");
            });
        }

        // Region filter
        if ($region = $request->input('region')) {
            $query->where('region', $region);
        }

        // RSM filter
        if ($rsm = $request->input('rsm')) {
            $query->where('rsm', $rsm);
        }

        // Dealer filter
        if ($dealer = $request->input('dealer')) {
            $query->where(function ($q) use ($dealer) {
                $q->where('dealer_code', $dealer)
                  ->orWhere('dealer_name', 'like', "%{$dealer}%");
            });
        }

        // Program filter
        if ($program = $request->input('program')) {
            $query->where('program_name', 'like', "%{$program}%");
        }

        // Invoice type filter (DSA / NPS FL)
        if ($type = $request->input('invoice_type')) {
            $query->where('invoice_type', $type);
        }

        // Status filter (ready, error, invoiced)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Date filter
        if ($date = $request->input('date')) {
            $query->where('invoice_date', $date);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $drafts = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($drafts);
    }

    /**
     * Get draft details with calculation comparison.
     */
    public function show($id): JsonResponse
    {
        $draft = Draft::with('invoice')->findOrFail($id);
        $comparison = $this->calculator->compare($draft);

        return response()->json([
            'draft' => $draft,
            'comparison' => $comparison,
        ]);
    }

    /**
     * Re-validate calculation against business rules.
     */
    public function validateDraft($id): JsonResponse
    {
        $draft = Draft::findOrFail($id);
        $comparison = $this->calculator->compare($draft);

        $notes = $draft->validation_notes ?? [];
        $notes['comparison'] = $comparison['fields'];
        $notes['is_matched'] = $comparison['is_matched'];
        $notes['calculated'] = $comparison['calculated'];

        // If invoice_type is invalid, keep error
        $invType = strtoupper(trim((string) $draft->invoice_type));
        if ($invType !== 'DSA' && $invType !== 'NPS FL') {
            $draft->status = 'error';
        } elseif ($draft->status !== 'invoiced') {
            $draft->status = 'ready';
        }

        $draft->validation_notes = $notes;
        $draft->save();

        return response()->json([
            'message' => 'Validasi berhasil dijalankan.',
            'draft' => $draft->load('invoice'),
            'comparison' => $comparison,
        ]);
    }
}
